<script lang="ts">
  import { getFrontPage, getPosts } from '../lib/api';
  import { updateSeo } from '../lib/seo';
  import Link from '../components/Link.svelte';
  import Loading from '../components/Loading.svelte';
  import type { WPPage, WPPost } from '../types';

  let frontPage: WPPage | null = $state(null);
  let posts: WPPost[] = $state([]);
  let loading = $state(true);
  let error: string | null = $state(null);
  let showTitle = $state(true);

  async function loadData() {
    try {
      loading = true;
      const [page, latestPosts] = await Promise.all([
        getFrontPage(),
        getPosts(1, 6),
      ]);
      frontPage = page;
      posts = latestPosts;
      showTitle = !frontPage?.meta?.kuh_hide_title;
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
</script>

{#if loading}
  <Loading />
{:else if error}
  <div class="max-w-4xl mx-auto px-4 py-12">
    <p class="text-red-600">Fehler: {error}</p>
  </div>
{:else}
  <!-- Hero / Frontpage Inhalt -->
  {#if frontPage}
    {#if showTitle}
      <section class="bg-gradient-to-br from-gray-50 to-gray-100 py-20">
        <div class="max-w-4xl mx-auto px-4 text-center">
          <h1 class="text-4xl md:text-5xl font-bold text-gray-900 mb-6">
            {@html frontPage.title.rendered}
          </h1>
        </div>
      </section>
    {/if}
    <section class="wp-content">
      <div class="prose prose-lg max-w-4xl mx-auto px-4">
        {@html frontPage.content.rendered}
      </div>
    </section>
  {:else}
    <section class="bg-gradient-to-br from-gray-50 to-gray-100 py-20">
      <div class="max-w-4xl mx-auto px-4 text-center">
        <h1 class="text-4xl md:text-5xl font-bold text-gray-900 mb-4">
          Willkommen
        </h1>
        <p class="text-xl text-gray-600">
          Entdecken Sie unser Angebot
        </p>
      </div>
    </section>
  {/if}

  <!-- Letzte Beiträge -->
  {#if posts.length > 0}
    <section class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-16">
      <h2 class="text-3xl font-bold text-gray-900 mb-8">Aktuelle Beiträge</h2>
      <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8">
        {#each posts as post}
          <article class="bg-white rounded-lg shadow-md overflow-hidden hover:shadow-lg transition-shadow">
            {#if post.featured_image_url}
              <img
                src={post.featured_image_url.medium}
                alt={post.title.rendered}
                class="w-full h-48 object-cover"
              />
            {/if}
            <div class="p-6">
              <h3 class="text-xl font-semibold text-gray-900 mb-2">
                <Link href="/post/{post.slug}" class="hover:text-[var(--color-secondary)] transition-colors">
                  {@html post.title.rendered}
                </Link>
              </h3>
              <div class="text-gray-600 text-sm mb-4 line-clamp-3">
                {@html post.excerpt.rendered}
              </div>
              <div class="flex justify-between items-center">
                <time class="text-gray-400 text-xs">
                  {new Date(post.date).toLocaleDateString('de-DE')}
                </time>
                <Link
                  href="/post/{post.slug}"
                  class="text-[var(--color-secondary)] hover:text-[var(--color-accent)] text-sm font-medium transition-colors"
                >
                  Weiterlesen →
                </Link>
              </div>
            </div>
          </article>
        {/each}
      </div>
    </section>
  {/if}
{/if}
