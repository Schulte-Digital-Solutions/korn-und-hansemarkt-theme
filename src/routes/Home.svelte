<script lang="ts">
  import { getFrontPage, getPosts } from '../lib/api';
  import { updateSeo } from '../lib/seo';
  import { reinitBlocks } from '../lib/reinitBlocks';
  import { updateAdminBar } from '../lib/adminBar';
  import { restoreScrollPosition } from '../lib/router';
  import { describeError, type ErrorPresentation } from '../lib/errors';
  import Loading from '../components/Loading.svelte';
  import ErrorState from '../components/ErrorState.svelte';
  import type { WPPage, WPPost } from '../types';

  let frontPage: WPPage | null = $state(null);
  let posts: WPPost[] = $state([]);
  let loading = $state(true);
  let error: ErrorPresentation | null = $state(null);

  async function loadData() {
    try {
      loading = true;
      error = null;
      const [page, latestPosts] = await Promise.all([
        getFrontPage(),
        getPosts(1, 6),
      ]);
      frontPage = page;
      posts = latestPosts;
      updateAdminBar(page?.id ?? null);
      updateSeo({
        title: '',
        description: window.kuhData?.siteDesc,
        canonical: window.kuhData?.homeUrl,
      });
    } catch (e) {
      error = describeError(e);
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
    onRetry={error.retryable ? loadData : null}
    showHome={false}
  />
{:else}

  <!-- WP-CONTENT: Die Custom-Blöcke (hero-section, highlights-grid, program-teaser, cta-section)
       werden automatisch via blockMounter als Svelte-Komponenten gemountet. -->
  {#if frontPage?.content?.rendered}
    <div class="wp-content front-page-content">
      {@html frontPage.content.rendered}
    </div>
  {/if}
{/if}
