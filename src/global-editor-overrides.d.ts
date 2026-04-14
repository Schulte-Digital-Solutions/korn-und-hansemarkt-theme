// Central fallback declarations for VS Code TS diagnostics in Svelte files.
export {};

declare global {
  const svelteHTML: {
    createElement: (...args: any[]) => any;
    mapElementTag?: (...args: any[]) => any;
  };

  function $state<T>(initial: T): T;
  function $state<T>(): T | undefined;

  function $effect(fn: () => void | (() => void)): void;
}
