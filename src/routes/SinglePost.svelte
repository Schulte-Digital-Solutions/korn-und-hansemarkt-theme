<script lang="ts">
  import { getPostBySlug } from '../lib/api';
  import { updateSeo } from '../lib/seo';
  import { reinitBlocks } from '../lib/reinitBlocks';
  import { updateAdminBar } from '../lib/adminBar';
  import { restoreScrollPosition } from '../lib/router';
  import Link from '../components/Link.svelte';
  import Loading from '../components/Loading.svelte';
  import type { WPPost } from '../types';

  interface Props {
    params: { slug: string };
  }

  let { params }: Props = $props();
  let post: WPPost | null = $state(null);
  let loading = $state(true);
  let error: string | null = $state(null);
  let showTitle = $state(true);

  async function loadPost(slug: string) {
    try {
      loading = true;
      error = null;
      post = await getPostBySlug(slug);
      showTitle = !post?.meta?.kuh_hide_title;
      updateAdminBar(post?.id ?? null);
      if (post) {
        updateSeo({
          title: post.title.rendered.replace(/<[^>]*>/g, ''),
          description: post.excerpt.rendered.replace(/<[^>]*>/g, '').trim() || post.content.rendered.replace(/<[^>]*>/g, '').slice(0, 160).trim(),
          ogType: 'article',
          ogImage: post.featured_image_url?.large,
          canonical: window.kuhData?.homeUrl?.replace(/\/$/, '') + '/post/' + slug,
        });
      } else {
        error = 'Beitrag nicht gefunden';
      }
    } catch (e) {
      error = e instanceof Error ? e.message : 'Fehler beim Laden';
    } finally {
      loading = false;
    }
  }

  $effect(() => {
    loadPost(params.slug);
  });

  $effect(() => {
    if (!loading && post) {
      reinitBlocks();
      restoreScrollPosition();
    }
  });
</script>

{#if loading}
  <Loading />
{:else if error}
  <div class="max-w-4xl mx-auto px-4 py-12">
    <p class="text-red-600 mb-4">{error}</p>
    <Link href="/blog" class="text-[var(--color-secondary)] hover:underline">
      ← Zurück zum Blog
    </Link>
  </div>
{:else if post}
  <article class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 py-4">
    <!-- Zurück-Link -->
    <Link href="/blog" class="text-[var(--color-secondary)] hover:underline text-sm mb-6 inline-block">
      ← Zurück zum Blog
    </Link>

    <!-- Featured Image -->
    {#if post.featured_image_url}
      <img
        src={post.featured_image_url.large}
        alt={post.title.rendered}
        class="w-full h-64 md:h-96 object-cover rounded-lg mb-8"
      />
    {/if}

    <!-- Titel -->
    {#if showTitle}
      <h1 class="text-3xl md:text-4xl font-bold text-gray-900 mb-4">
        {@html post.title.rendered}
      </h1>
    {/if}

    <!-- Meta -->
    <div class="flex items-center gap-4 text-gray-500 text-sm mb-8 pb-8 border-b border-gray-200">
      <time>{new Date(post.date).toLocaleDateString('de-DE', {
        year: 'numeric',
        month: 'long',
        day: 'numeric',
      })}</time>
    </div>

    <!-- Inhalt -->
    <div class="prose prose-lg max-w-none">
      {@html post.content.rendered}
    </div>
  </article>
{/if}
