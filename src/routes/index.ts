import Home from './Home.svelte';
import Blog from './Blog.svelte';
import SinglePost from './SinglePost.svelte';
import Page from './Page.svelte';
import NotFound from './NotFound.svelte';
import type { Route } from '../lib/router';

export const routes: Route[] = [
  { path: '/', component: Home },
  { path: '/blog', component: Blog },
  { path: '/post/:slug', component: SinglePost },
  { path: '/:slug', component: Page },
  { path: '*', component: NotFound },
];
