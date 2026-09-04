<script lang="ts">
  import { getPageBySlug } from '../lib/api';
  import { updateSeo } from '../lib/seo';
  import { reinitBlocks } from '../lib/reinitBlocks';
  import { updateAdminBar } from '../lib/adminBar';
  import { restoreScrollPosition } from '../lib/router';
  import { describeError, notFoundPresentation, type ErrorPresentation } from '../lib/errors';
  import Loading from '../components/Loading.svelte';
  import ErrorState from '../components/ErrorState.svelte';
  import type { WPPage } from '../types';

  interface Props {
    params: { path: string };
  }

  let { params }: Props = $props();
  let page: WPPage | null = $state(null);
  let loading = $state(true);
  let error: ErrorPresentation | null = $state(null);
  let showTitle = $state(true);
  let fullWidth = $state(false);

  async function loadPage(path: string) {
    try {
      loading = true;
      error = null;
      const slug = path.split('/').filter(Boolean).pop() ?? '';
      page = await getPageBySlug(slug);
      showTitle = !page?.meta?.kuh_hide_title;
      fullWidth = Boolean(page?.meta?.kuh_full_width);
      updateAdminBar(page?.id ?? null);
      if (page) {
        updateSeo({
          title: page.title.rendered.replace(/<[^>]*>/g, ''),
          description: page.content.rendered.replace(/<[^>]*>/g, '').slice(0, 160).trim(),
          ogImage: page.featured_image_url?.large,
          canonical: window.kuhData?.homeUrl?.replace(/\/$/, '') + '/' + path,
        });
      } else {
        error = notFoundPresentation('Seite');
      }
    } catch (e) {
      error = describeError(e);
    } finally {
      loading = false;
    }
  }

  $effect(() => {
    loadPage(params.path);
  });

  $effect(() => {
    if (!loading && page) {
      reinitBlocks();
      restoreScrollPosition();
    }
  });
</script>

{#if loading}
  <Loading />
{:else if error}
  <ErrorState
    code={error.code}
    icon={error.icon}
    title={error.title}
    description={error.description}
    onRetry={error.retryable ? () => loadPage(params.path) : null}
  />
{:else if page}
  <article class={fullWidth ? 'w-full py-0' : 'max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 py-0'}>
    {#if page.featured_image_url}
      <img
        src={page.featured_image_url.large}
        alt={page.title.rendered}
        class={fullWidth
          ? 'w-full h-64 md:h-96 object-cover mb-8'
          : 'w-full h-64 md:h-96 object-cover rounded-lg mb-8'}
      />
    {/if}

    {#if showTitle}
      <h1 class={fullWidth
        ? 'mx-auto max-w-4xl px-4 sm:px-6 lg:px-8 text-3xl md:text-4xl font-bold text-gray-900 mb-8'
        : 'text-3xl md:text-4xl font-bold text-gray-900 mb-8'}>
        {@html page.title.rendered}
      </h1>
    {/if}

    {#if fullWidth}
      <div class="wp-content page-content-full">
        {@html page.content.rendered}
      </div>
    {:else}
      <div class="prose prose-lg max-w-none">
        {@html page.content.rendered}
      </div>
    {/if}
  </article>
{/if}
