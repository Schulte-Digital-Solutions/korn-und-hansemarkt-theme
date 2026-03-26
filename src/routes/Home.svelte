<script lang="ts">
  import { getFrontPage, getPosts } from '../lib/api';
  import { updateSeo } from '../lib/seo';
  import { reinitBlocks } from '../lib/reinitBlocks';
  import Loading from '../components/Loading.svelte';
  import type { WPPage, WPPost } from '../types';

  let frontPage: WPPage | null = $state(null);
  let posts: WPPost[] = $state([]);
  let loading = $state(true);
  let error: string | null = $state(null);

  async function loadData() {
    try {
      loading = true;
      const [page, latestPosts] = await Promise.all([
        getFrontPage(),
        getPosts(1, 6),
      ]);
      frontPage = page;
      posts = latestPosts;
      updateSeo({
        title: '',
        description: window.kuhData?.siteDesc,
        canonical: window.kuhData?.homeUrl,
      });
    } catch (e) {
      error = e instanceof Error ? e.message : 'Fehler beim Laden';
    } finally {
      loading = false;
    }
  }

  $effect(() => {
    loadData();
  });

  $effect(() => {
    if (!loading && frontPage) {
      reinitBlocks();
    }
  });
</script>

{#if loading}
  <Loading />
{:else if error}
  <div class="max-w-4xl mx-auto px-4 py-12">
    <p class="text-red-600">Fehler: {error}</p>
  </div>
{:else}

  <!-- WP-CONTENT: Die Custom-Blöcke (hero-section, highlights-grid, program-teaser, cta-section)
       werden automatisch via blockMounter als Svelte-Komponenten gemountet. -->
  {#if frontPage?.content?.rendered}
    <div class="wp-content front-page-content">
      {@html frontPage.content.rendered}
    </div>
  {/if}
{/if}
