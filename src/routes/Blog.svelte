<script lang="ts">
  import { getPosts } from '../lib/api';
  import { updateSeo } from '../lib/seo';
  import { updateAdminBar } from '../lib/adminBar';
  import { restoreScrollPosition } from '../lib/router';
  import { describeError, type ErrorPresentation } from '../lib/errors';
  import Link from '../components/Link.svelte';
  import Loading from '../components/Loading.svelte';
  import ErrorState from '../components/ErrorState.svelte';
  import type { WPPost } from '../types';

  let posts: WPPost[] = $state([]);
  let loading = $state(true);
  let error: ErrorPresentation | null = $state(null);
  let page = $state(1);
  let hasMore = $state(true);

  async function loadPosts(pageNum: number) {
    try {
      loading = true;
      error = null;
      const newPosts = await getPosts(pageNum, 10);
      posts = pageNum === 1 ? newPosts : [...posts, ...newPosts];
      hasMore = newPosts.length === 10;
      if (pageNum === 1) {
        updateAdminBar(null);
        updateSeo({
          title: 'Blog',
          description: 'Aktuelle Beiträge und Neuigkeiten',
          canonical: window.kuhData?.homeUrl?.replace(/\/$/, '') + '/blog',
        });
      }
    } catch (e) {
      error = describeError(e);
    } finally {
      loading = false;
    }
  }

  function loadMore() {
    page += 1;
    loadPosts(page);
  }

  $effect(() => {
    loadPosts(1);
  });

  $effect(() => {
    if (!loading && posts.length > 0) {
      restoreScrollPosition();
    }
  });
</script>

{#if error && posts.length === 0 && !loading}
  <!-- Nichts geladen: ganzseitiger Fehlerzustand -->
  <ErrorState
    code={error.code}
    icon={error.icon}
    title={error.title}
    description={error.description}
    onRetry={error.retryable ? () => loadPosts(1) : null}
  />
{:else}
<div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 py-4">
  <h1 class="text-3xl font-bold text-gray-900 mb-8">Blog</h1>

  {#if error}
    <!-- Nachladen fehlgeschlagen: schmaler Hinweis über der bestehenden Liste -->
    <div
      class="mb-8 flex flex-wrap items-center gap-3 rounded-lg bg-error-container px-4 py-3 text-sm text-on-error-container"
      role="alert"
    >
      <span class="material-symbols-outlined !text-xl" aria-hidden="true">{error.icon}</span>
      <span>{error.description}</span>
      {#if error.retryable}
        <button
          type="button"
          onclick={() => loadPosts(page)}
          class="ml-auto inline-flex items-center gap-1 rounded-full px-3 py-1 font-medium underline underline-offset-4 transition-colors hover:bg-on-error-container/10"
        >
          <span class="material-symbols-outlined !text-base" aria-hidden="true">refresh</span>
          Erneut versuchen
        </button>
      {/if}
    </div>
  {/if}

  <div class="space-y-8">
    {#each posts as post}
      <article class="bg-white rounded-lg shadow-md overflow-hidden hover:shadow-lg transition-shadow">
        <div class="flex flex-col md:flex-row">
          {#if post.featured_image_url}
            <div class="md:w-1/3 flex-shrink-0">
              <img
                src={post.featured_image_url.medium}
                alt={post.title.rendered}
                class="w-full h-48 md:h-full object-cover"
              />
            </div>
          {/if}
          <div class="p-6 flex-1">
            <h2 class="text-xl font-semibold text-gray-900 mb-2">
              <Link href="/post/{post.slug}" class="hover:text-[var(--color-secondary)] transition-colors">
                {@html post.title.rendered}
              </Link>
            </h2>
            <time class="text-gray-400 text-xs mb-3 block">
              {new Date(post.date).toLocaleDateString('de-DE')}
            </time>
            <div class="text-gray-600 text-sm line-clamp-3">
              {@html post.excerpt.rendered}
            </div>
            <Link
              href="/post/{post.slug}"
              class="inline-block mt-4 text-secondary hover:text-primary text-sm font-medium transition-colors"
            >
              Weiterlesen →
            </Link>
          </div>
        </div>
      </article>
    {/each}
  </div>

  {#if loading}
    <Loading />
  {/if}

  {#if hasMore && !loading && !error}
    <div class="text-center mt-8">
      <button
        onclick={loadMore}
        class="px-6 py-3 bg-secondary text-white rounded-lg hover:bg-primary-container transition-colors font-medium"
      >
        Mehr laden
      </button>
    </div>
  {/if}
</div>
{/if}
