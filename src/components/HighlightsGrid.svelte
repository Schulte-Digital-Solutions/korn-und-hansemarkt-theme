<script lang="ts">
  interface Highlight {
    value: string;
    title: string;
    description: string;
  }

  interface Props {
    highlights: Highlight[];
  }

  let { highlights }: Props = $props();
</script>

<section class="py-12 px-6 max-w-7xl mx-auto grid grid-cols-1 md:grid-cols-3 gap-6">
  {#each highlights as item}
    <div class="bg-surface-container-low p-10 rounded-xl flex flex-col justify-between group hover:bg-surface-container transition-colors">
      <span class="text-6xl font-headline text-secondary mb-8">{item.value}</span>
      <div class="space-y-2">
        <h3 class="text-xl font-bold uppercase tracking-widest text-primary">{item.title}</h3>
        <p class="text-sm text-on-surface/60">{item.description}</p>
      </div>
    </div>
  {/each}
</section>
