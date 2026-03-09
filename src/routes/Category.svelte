<script lang="ts">
  import { getCategoryBySlug, getPostsByCategory } from '../lib/api';
  import { updateSeo } from '../lib/seo';
  import Link from '../components/Link.svelte';
  import Loading from '../components/Loading.svelte';
  import type { WPPost } from '../types';

  interface Props {
    params: { slug: string };
  }

  let { params }: Props = $props();
  let categoryName = $state('');
  let posts: WPPost[] = $state([]);
  let loading = $state(true);
  let error: string | null = $state(null);
  let page = $state(1);
  let hasMore = $state(true);

  async function loadCategory(slug: string) {
    try {
      loading = true;
      error = null;
      page = 1;

      const category = await getCategoryBySlug(slug);
      if (!category) {
        error = 'Kategorie nicht gefunden';
        return;
      }

      categoryName = category.name;
      const newPosts = await getPostsByCategory(category.id, 1, 10);
      posts = newPosts;
      hasMore = newPosts.length === 10;

      updateSeo({
        title: `Kategorie: ${category.name}`,
        description: category.description || `Beiträge in der Kategorie „${category.name}"`,
        canonical: window.kuhData?.homeUrl?.replace(/\/$/, '') + '/category/' + slug,
      });
    } catch (e) {
      error = e instanceof Error ? e.message : 'Fehler beim Laden';
    } finally {
      loading = false;
    }
  }

  async function loadMore() {
    const category = await getCategoryBySlug(params.slug);
    if (!category) return;
    page += 1;
    const newPosts = await getPostsByCategory(category.id, page, 10);
    posts = [...posts, ...newPosts];
    hasMore = newPosts.length === 10;
  }

  $effect(() => {
    loadCategory(params.slug);
  });
</script>

{#if loading}
  <Loading />
{:else if error}
  <div class="max-w-4xl mx-auto px-4 py-12">
    <p class="text-red-600">{error}</p>
  </div>
{:else}
  <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 py-12">
    <h1 class="text-3xl font-bold text-gray-900 mb-8">Kategorie: {categoryName}</h1>

    {#if posts.length === 0}
      <p class="text-gray-500">Keine Beiträge in dieser Kategorie.</p>
    {:else}
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
                  class="inline-block mt-4 text-[var(--color-secondary)] hover:text-[var(--color-accent)] text-sm font-medium transition-colors"
                >
                  Weiterlesen →
                </Link>
              </div>
            </div>
          </article>
        {/each}
      </div>

      {#if hasMore}
        <div class="text-center mt-8">
          <button
            onclick={loadMore}
            class="px-6 py-3 bg-[var(--color-secondary)] text-white rounded-lg hover:bg-[var(--color-accent)] transition-colors"
          >
            Mehr laden
          </button>
        </div>
      {/if}
    {/if}
  </div>
{/if}
