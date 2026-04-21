<script lang="ts">
  interface ProgramEvent {
    time: string;
    title: string;
    description: string;
  }

  interface ProgramDay {
    label: string;
    date: string;
    events: ProgramEvent[];
  }

  interface Props {
    days: ProgramDay[];
    /** Gerenderter InnerBlocks-HTML-Content (z.B. Link "Ganzes Programm") */
    contentHtml: string;
  }

  let { days, contentHtml }: Props = $props();

  let activeDay = $state(0);
</script>

<section class="py-24 px-6 max-w-7xl mx-auto">
  <h2 class="text-5xl font-headline text-primary mb-12">Programm</h2>
  <div class="grid grid-cols-1 lg:grid-cols-12 gap-12">
    <!-- Day tabs -->
    <div class="lg:col-span-4 space-y-4">
      {#each days as day, i}
        <button
          onclick={() => activeDay = i}
          class="w-full text-left p-6 rounded-lg transition-all {activeDay === i
            ? 'bg-emerald-50 border-l-4 border-secondary'
            : 'hover:bg-surface-container border-l-4 border-transparent'}"
        >
          <span class="block text-xs font-bold uppercase tracking-widest {activeDay === i ? 'text-secondary' : 'text-stone-500'} mb-1">
            {day.date}
          </span>
          <span class="text-2xl font-bold {activeDay === i ? 'text-primary' : 'text-stone-400'}">
            {day.label}
          </span>
        </button>
      {/each}
    </div>

    <!-- Events for active day -->
    <div class="lg:col-span-8">
      <div class="space-y-8">
        {#each days[activeDay]?.events ?? [] as event}
          <div class="flex gap-6 pb-8 border-b border-outline/10">
            <span class="text-secondary font-bold text-lg min-w-15">{event.time}</span>
            <div>
              <h4 class="text-xl font-bold text-on-surface">{event.title}</h4>
              <p class="text-on-surface/70 mt-2">{event.description}</p>
            </div>
          </div>
        {/each}

        {#if contentHtml}
          <div class="pt-4 program-teaser-content">
            {@html contentHtml}
          </div>
        {/if}
      </div>
    </div>
  </div>
</section>

