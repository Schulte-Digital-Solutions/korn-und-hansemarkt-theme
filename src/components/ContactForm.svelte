<script lang="ts">
  import { onMount } from 'svelte';
  import { sendContactForm } from '../lib/api';

  type FieldType = 'text' | 'email' | 'number' | 'tel' | 'textarea' | 'select' | 'checkbox';

  interface FormField {
    id: string;
    name: string;
    label: string;
    type: FieldType;
    required: boolean;
    placeholder?: string;
    options?: string[];
    cols?: 1 | 2 | 4;
  }

  interface Props {
    subject?: string;
    recipientEmail?: string;
    recipientToken?: string;
    fields?: FormField[];
    fieldsToken?: string;
    formTitle?: string;
    formIntro?: string;
    submitLabel?: string;
    successMessage?: string;
    privacyNote?: string;
  }

  let {
    subject = 'Kontaktanfrage',
    recipientEmail = '',
    recipientToken = '',
    fields = [],
    fieldsToken = '',
    formTitle = '',
    formIntro = '',
    submitLabel = 'Nachricht senden',
    successMessage = 'Vielen Dank! Deine Nachricht wurde gesendet.',
    privacyNote = '',
  }: Props = $props();

  let fieldValues = $state<Record<string, string | boolean>>({});
  let website = $state('');
  let status = $state<'idle' | 'loading' | 'success' | 'error'>('idle');
  let feedback = $state('');
  let formStartedAt = $state(Math.floor(Date.now() / 1000));

  let captchaContainer = $state<HTMLDivElement | null>(null);
  let captchaWidgetId = $state<string | number | null>(null);
  let hcaptchaToken = $state('');
  let hcaptchaTheme = $state<'light' | 'dark'>('light');
  let themeObserver: MutationObserver | null = null;

  const hcaptchaEnabled = Boolean(window.kuhData?.contact?.hcaptchaEnabled);
  const hcaptchaSiteKey = window.kuhData?.contact?.hcaptchaSiteKey ?? '';

  function isValidEmail(value: string): boolean {
    return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(value);
  }

  function getFields(): FormField[] {
    if (Array.isArray(fields) && fields.length > 0) {
      return fields;
    }

    return [
      { id: 'name', name: 'name', label: 'Name', type: 'text', required: true, placeholder: '', cols: 2 },
      { id: 'email', name: 'email', label: 'E-Mail', type: 'email', required: true, placeholder: '', cols: 2 },
      { id: 'message', name: 'message', label: 'Nachricht', type: 'textarea', required: true, placeholder: '', cols: 4 },
    ];
  }

  function colSpanClass(field: FormField): string {
    const c = field.cols ?? (field.type === 'textarea' || field.type === 'checkbox' ? 4 : 2);
    if (c === 1) return 'md:col-span-1';
    if (c === 4) return 'md:col-span-4';
    return 'md:col-span-2';
  }

  function initFieldValues(): void {
    const initial: Record<string, string | boolean> = {};

    for (const field of getFields()) {
      if (field.type === 'checkbox') {
        initial[field.id] = false;
      } else {
        initial[field.id] = '';
      }
    }

    fieldValues = initial;
  }

  function getStringValue(fieldId: string): string {
    const raw = fieldValues[fieldId];
    return typeof raw === 'string' ? raw : '';
  }

  function setStringValue(fieldId: string, value: string): void {
    fieldValues = { ...fieldValues, [fieldId]: value };
  }

  function getBoolValue(fieldId: string): boolean {
    return Boolean(fieldValues[fieldId]);
  }

  function setBoolValue(fieldId: string, value: boolean): void {
    fieldValues = { ...fieldValues, [fieldId]: value };
  }

  function loadHcaptchaScript(): Promise<void> {
    return new Promise((resolve, reject) => {
      const existing = document.querySelector<HTMLScriptElement>('script[data-kuh-hcaptcha="true"]');
      if (existing) {
        if (window.hcaptcha) {
          resolve();
          return;
        }
        existing.addEventListener('load', () => resolve(), { once: true });
        existing.addEventListener('error', () => reject(new Error('hCaptcha konnte nicht geladen werden.')), { once: true });
        return;
      }

      const script = document.createElement('script');
      script.src = 'https://js.hcaptcha.com/1/api.js?render=explicit';
      script.async = true;
      script.defer = true;
      script.dataset.kuhHcaptcha = 'true';
      script.onload = () => resolve();
      script.onerror = () => reject(new Error('hCaptcha konnte nicht geladen werden.'));
      document.head.appendChild(script);
    });
  }

  function getHcaptchaTheme(): 'light' | 'dark' {
    return document.documentElement.classList.contains('dark') ? 'dark' : 'light';
  }

  function renderHcaptchaWidget(): void {
    if (!window.hcaptcha || !captchaContainer) return;

    if (captchaWidgetId !== null) {
      window.hcaptcha.remove(captchaWidgetId);
      captchaWidgetId = null;
    }

    hcaptchaTheme = getHcaptchaTheme();

    captchaWidgetId = window.hcaptcha.render(captchaContainer, {
      sitekey: hcaptchaSiteKey,
      theme: hcaptchaTheme,
      callback: (token: string) => {
        hcaptchaToken = token;
      },
      'expired-callback': () => {
        hcaptchaToken = '';
      },
      'error-callback': () => {
        hcaptchaToken = '';
      },
    } as any);
  }

  async function initHcaptcha() {
    if (!hcaptchaEnabled || !hcaptchaSiteKey || !captchaContainer) return;
    await loadHcaptchaScript();

    if (!window.hcaptcha) {
      throw new Error('hCaptcha API nicht verfuegbar.');
    }

    renderHcaptchaWidget();
  }

  onMount(() => {
    initFieldValues();

    initHcaptcha().catch((e: unknown) => {
      feedback = e instanceof Error ? e.message : 'hCaptcha konnte nicht initialisiert werden.';
      status = 'error';
    });

    if (hcaptchaEnabled && hcaptchaSiteKey) {
      themeObserver = new MutationObserver(() => {
        const nextTheme = getHcaptchaTheme();
        if (nextTheme !== hcaptchaTheme) {
          hcaptchaToken = '';
          renderHcaptchaWidget();
        }
      });

      themeObserver.observe(document.documentElement, {
        attributes: true,
        attributeFilter: ['class'],
      });
    }

    return () => {
      themeObserver?.disconnect();

      if (window.hcaptcha && captchaWidgetId !== null) {
        window.hcaptcha.remove(captchaWidgetId);
      }
    };
  });

  async function onSubmit(event: SubmitEvent) {
    event.preventDefault();

    feedback = '';

    const currentFields = getFields();

    for (const field of currentFields) {
      const rawValue = fieldValues[field.id];
      const value = field.type === 'checkbox' ? Boolean(rawValue) : String(rawValue ?? '').trim();

      if (field.required) {
        const isEmpty = field.type === 'checkbox' ? !value : value === '';
        if (isEmpty) {
          status = 'error';
          feedback = 'Bitte alle Pflichtfelder ausfuellen.';
          return;
        }
      }

      if (field.type === 'email' && value !== '' && !isValidEmail(String(value))) {
        status = 'error';
        feedback = 'Bitte eine gueltige E-Mail-Adresse eingeben.';
        return;
      }

      if (field.type === 'number' && value !== '' && Number.isNaN(Number(value))) {
        status = 'error';
        feedback = 'Bitte eine gueltige Zahl eingeben.';
        return;
      }
    }

    if (hcaptchaEnabled && !hcaptchaToken) {
      status = 'error';
      feedback = 'Bitte bestaetige die Captcha-Pruefung.';
      return;
    }

    status = 'loading';

    try {
      const payloadFields = currentFields.map((field) => ({
        id: field.id,
        name: field.name,
        label: field.label,
        type: field.type,
        required: Boolean(field.required),
        placeholder: field.placeholder ?? '',
        options: Array.isArray(field.options) ? field.options : [],
        value: field.type === 'checkbox' ? getBoolValue(field.id) : getStringValue(field.id).trim(),
      }));

      const firstEmail = payloadFields.find((field) => field.type === 'email' && typeof field.value === 'string' && field.value !== '');
      const firstName = payloadFields.find((field) => (field.type === 'text' || field.type === 'textarea') && typeof field.value === 'string' && field.value !== '');
      const firstMessage = payloadFields.find((field) => field.type === 'textarea' && typeof field.value === 'string' && field.value !== '');

      const result = await sendContactForm({
        name: typeof firstName?.value === 'string' ? firstName.value : '',
        email: typeof firstEmail?.value === 'string' ? firstEmail.value : '',
        subject: subject.trim(),
        message: typeof firstMessage?.value === 'string' ? firstMessage.value : '',
        fields: payloadFields,
        fieldsToken: fieldsToken || undefined,
        website,
        formStartedAt,
        recipientEmail: recipientEmail.trim() || undefined,
        recipientToken: recipientToken.trim() || undefined,
        hcaptchaToken: hcaptchaEnabled ? hcaptchaToken : undefined,
      });

      status = 'success';
        feedback = successMessage.trim() || result.message || 'Vielen Dank! Deine Nachricht wurde gesendet.';

        initFieldValues();
      website = '';
      hcaptchaToken = '';
      formStartedAt = Math.floor(Date.now() / 1000);

      if (window.hcaptcha && captchaWidgetId !== null) {
        window.hcaptcha.reset(captchaWidgetId);
      }
    } catch (e) {
      status = 'error';
      feedback = e instanceof Error ? e.message : 'Senden fehlgeschlagen.';

      if (window.hcaptcha && captchaWidgetId !== null) {
        window.hcaptcha.reset(captchaWidgetId);
      }
    }
  }
</script>

<form onsubmit={onSubmit} class="space-y-5 rounded-2xl border border-stone-200/80 bg-white p-6 shadow-sm dark:border-white/10 dark:bg-surface-container-low" novalidate>
  {#if formTitle}
    <h3 class="text-xl font-semibold text-emerald-900 dark:text-primary">{formTitle}</h3>
  {/if}

  {#if formIntro}
    <p class="text-sm leading-6 text-stone-700 dark:text-on-surface/80">{formIntro}</p>
  {/if}

  <div class="grid gap-5 md:grid-cols-4">
    {#each getFields() as field (field.id)}
      {#if field.type === 'textarea'}
        <label class="flex flex-col gap-2 {colSpanClass(field)}">
          <span class="text-sm font-semibold text-stone-800 dark:text-on-surface">{field.label}{field.required ? ' *' : ''}</span>
          <textarea
            value={getStringValue(field.id)}
            oninput={(event: Event) => setStringValue(field.id, (event.currentTarget as HTMLTextAreaElement).value)}
            placeholder={field.placeholder ?? ''}
            rows="6"
            required={field.required}
            class="rounded-xl border border-stone-300 bg-white px-4 py-3 text-stone-900 outline-none transition placeholder:text-stone-500 focus:border-emerald-700 focus:ring-2 focus:ring-emerald-100 dark:border-white/15 dark:bg-surface-container dark:text-on-surface dark:placeholder:text-on-surface/50 dark:focus:border-primary dark:focus:ring-white/10"
          ></textarea>
        </label>
      {:else if field.type === 'select'}
        <label class="flex flex-col gap-2 {colSpanClass(field)}">
          <span class="text-sm font-semibold text-stone-800 dark:text-on-surface">{field.label}{field.required ? ' *' : ''}</span>
          <select
            value={getStringValue(field.id)}
            oninput={(event: Event) => setStringValue(field.id, (event.currentTarget as HTMLSelectElement).value)}
            required={field.required}
            class="rounded-xl border border-stone-300 bg-white px-4 py-3 text-stone-900 outline-none transition focus:border-emerald-700 focus:ring-2 focus:ring-emerald-100 dark:border-white/15 dark:bg-surface-container dark:text-on-surface dark:focus:border-primary dark:focus:ring-white/10"
          >
            <option value="">Bitte auswählen</option>
            {#each field.options ?? [] as option}
              <option value={option}>{option}</option>
            {/each}
          </select>
        </label>
      {:else if field.type === 'checkbox'}
        <label class="flex cursor-pointer items-start gap-3 {colSpanClass(field)}">
          <span class="relative mt-0.5 flex h-5 w-5 shrink-0 items-center justify-center">
            <input
              type="checkbox"
              checked={getBoolValue(field.id)}
              onchange={(event: Event) => setBoolValue(field.id, (event.currentTarget as HTMLInputElement).checked)}
              required={field.required}
              class="sr-only"
            />
            <span class={getBoolValue(field.id)
              ? 'flex h-5 w-5 items-center justify-center rounded border-2 border-emerald-900 bg-emerald-900 transition dark:border-primary dark:bg-primary dark:text-on-primary'
              : 'flex h-5 w-5 items-center justify-center rounded border-2 border-stone-300 bg-white transition hover:border-emerald-700 dark:border-white/20 dark:bg-surface-container dark:hover:border-primary'
            }>
              {#if getBoolValue(field.id)}
                <svg class="h-3 w-3 text-white" viewBox="0 0 12 12" fill="none" aria-hidden="true">
                  <polyline points="2,6 5,9 10,3" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" />
                </svg>
              {/if}
            </span>
          </span>
          <span class="text-sm leading-5 text-stone-800 dark:text-on-surface">{field.label}{field.required ? ' *' : ''}</span>
        </label>
      {:else}
        <label class="flex flex-col gap-2 {colSpanClass(field)}">
          <span class="text-sm font-semibold text-stone-800 dark:text-on-surface">{field.label}{field.required ? ' *' : ''}</span>
          <input
            type={field.type}
            value={getStringValue(field.id)}
            oninput={(event: Event) => setStringValue(field.id, (event.currentTarget as HTMLInputElement).value)}
            placeholder={field.placeholder ?? ''}
            required={field.required}
            class="rounded-xl border border-stone-300 bg-white px-4 py-3 text-stone-900 outline-none transition placeholder:text-stone-500 focus:border-emerald-700 focus:ring-2 focus:ring-emerald-100 dark:border-white/15 dark:bg-surface-container dark:text-on-surface dark:placeholder:text-on-surface/50 dark:focus:border-primary dark:focus:ring-white/10"
          />
        </label>
      {/if}
    {/each}
  </div>

  <label class="sr-only" aria-hidden="true">
    Website
    <input type="text" tabindex="-1" autocomplete="off" bind:value={website} />
  </label>

  {#if hcaptchaEnabled && hcaptchaSiteKey}
    <div bind:this={captchaContainer} class="min-h-20"></div>
  {/if}

  <button
    type="submit"
    disabled={status === 'loading'}
    class="inline-flex items-center justify-center rounded-xl bg-emerald-900 px-5 py-3 font-semibold text-white transition hover:bg-emerald-800 disabled:cursor-not-allowed disabled:opacity-60 dark:bg-primary dark:text-on-primary dark:hover:brightness-110"
  >
    {#if status === 'loading'}
      Wird gesendet...
    {:else}
      {submitLabel}
    {/if}
  </button>

  {#if privacyNote}
    <p class="text-xs leading-5 text-stone-600 dark:text-on-surface/65">{privacyNote}</p>
  {/if}

  {#if feedback}
    <p class={status === 'success' ? 'text-sm text-emerald-700 dark:text-secondary' : 'text-sm text-red-700 dark:text-red-400'}>{feedback}</p>
  {/if}
</form>
