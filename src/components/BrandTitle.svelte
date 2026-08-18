<script lang="ts">
  interface Props {
    title: string;
  }

  let { title }: Props = $props();

  function decodeHtml(value: string): string {
    const element = document.createElement('textarea');
    element.innerHTML = value;
    return element.value;
  }

  const titleParts = $derived.by(() => {
    const normalizedTitle = decodeHtml(title).replace(/[\u2011\u2013\u2014\u2212]/g, '-');
    const separatorIndex = normalizedTitle.indexOf('-');

    if (separatorIndex === -1) return null;

    return {
      left: normalizedTitle.slice(0, separatorIndex),
      right: normalizedTitle.slice(separatorIndex + 1),
    };
  });
</script>

{#if titleParts}
  <span>{titleParts.left}</span><span style="font-family: 'Inter', sans-serif;">-</span><span>{titleParts.right}</span>
{:else}
  {decodeHtml(title)}
{/if}