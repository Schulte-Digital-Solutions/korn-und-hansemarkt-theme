<script lang="ts">
  import Router from './components/Router.svelte';
  import { routes } from './routes';
  import Header from './components/Header.svelte';
  import Footer from './components/Footer.svelte';
  import MobileBottomNav from './components/MobileBottomNav.svelte';
</script>

<div class="flex flex-col min-h-screen">
  <Header />

  <main class="flex-1">
    <Router {routes} />
  </main>

  <Footer />

  <MobileBottomNav />
</div>
