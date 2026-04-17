<script lang="ts">
  import { getCategories, type WPCategory } from '../lib/api';
  import { updateSeo } from '../lib/seo';
  import { updateAdminBar } from '../lib/adminBar';
  import { restoreScrollPosition } from '../lib/router';
  import Link from '../components/Link.svelte';
  import Loading from '../components/Loading.svelte';

  let categories: WPCategory[] = $state([]);
  let loading = $state(true);
  let error: string | null = $state(null);

  async function loadCategories() {
    try {
      loading = true;
      categories = await getCategories();
      updateAdminBar(null);
      updateSeo({
        title: 'Kategorien',
        description: 'Alle Beitragskategorien im Überblick',
        canonical: window.kuhData?.homeUrl?.replace(/\/$/, '') + '/category',
      });
    } catch (e) {
      error = e instanceof Error ? e.message : 'Fehler beim Laden';
    } finally {
      loading = false;
    }
  }

  $effect(() => {
    loadCategories();
  });

  $effect(() => {
    if (!loading && categories.length > 0) {
      restoreScrollPosition();
    }
  });
</script>

{#if loading}
  <Loading />
{:else if error}
  <div class="max-w-4xl mx-auto px-4 py-12">
    <p class="text-red-600">{error}</p>
  </div>
{:else}
  <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 py-4">
    <h1 class="text-3xl font-bold text-gray-900 mb-8">Kategorien</h1>

    {#if categories.length === 0}
      <p class="text-gray-500">Keine Kategorien vorhanden.</p>
    {:else}
      <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6">
        {#each categories as cat}
          <Link
            href="/category/{cat.slug}"
            class="block bg-white rounded-lg shadow-md p-6 hover:shadow-lg transition-shadow"
          >
            <h2 class="text-xl font-semibold text-gray-900 mb-2">{cat.name}</h2>
            {#if cat.description}
              <p class="text-gray-600 text-sm mb-3">{cat.description}</p>
            {/if}
            <span class="text-[var(--color-secondary)] text-sm font-medium">
              {cat.count} {cat.count === 1 ? 'Beitrag' : 'Beiträge'}
            </span>
          </Link>
        {/each}
      </div>
    {/if}
  </div>
{/if}
