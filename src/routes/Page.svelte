<script lang="ts">
  import { getPageBySlug } from '../lib/api';
  import { updateSeo } from '../lib/seo';
  import { reinitBlocks } from '../lib/reinitBlocks';
  import Loading from '../components/Loading.svelte';
  import type { WPPage } from '../types';

  interface Props {
    params: { slug: string };
  }

  let { params }: Props = $props();
  let page: WPPage | null = $state(null);
  let loading = $state(true);
  let error: string | null = $state(null);
  let showTitle = $state(true);

  async function loadPage(slug: string) {
    try {
      loading = true;
      error = null;
      page = await getPageBySlug(slug);
      showTitle = !page?.meta?.kuh_hide_title;
      if (page) {
        updateSeo({
          title: page.title.rendered.replace(/<[^>]*>/g, ''),
          description: page.content.rendered.replace(/<[^>]*>/g, '').slice(0, 160).trim(),
          ogImage: page.featured_image_url?.large,
          canonical: window.kuhData?.homeUrl?.replace(/\/$/, '') + '/' + slug,
        });
      } else {
        error = 'Seite nicht gefunden';
      }
    } catch (e) {
      error = e instanceof Error ? e.message : 'Fehler beim Laden';
    } finally {
      loading = false;
    }
  }

  $effect(() => {
    loadPage(params.slug);
  });

  $effect(() => {
    if (!loading && page) {
      reinitBlocks();
    }
  });
</script>

{#if loading}
  <Loading />
{:else if error}
  <div class="max-w-4xl mx-auto px-4 py-12">
    <p class="text-red-600">{error}</p>
  </div>
{:else if page}
  <article class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 py-12">
    {#if page.featured_image_url}
      <img
        src={page.featured_image_url.large}
        alt={page.title.rendered}
        class="w-full h-64 md:h-96 object-cover rounded-lg mb-8"
      />
    {/if}

    {#if showTitle}
      <h1 class="text-3xl md:text-4xl font-bold text-gray-900 mb-8">
        {@html page.title.rendered}
      </h1>
    {/if}

    <div class="prose prose-lg max-w-none">
      {@html page.content.rendered}
    </div>
  </article>
{/if}
