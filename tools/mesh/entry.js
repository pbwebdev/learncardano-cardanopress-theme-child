// Entry point for the self-hosted Mesh SDK bundle used by the delegate page.
// Only re-exports what wallet connect + delegation needs, so esbuild
// tree-shakes the rest of Mesh away. No chain provider: protocol parameters
// come from the theme's REST endpoint and the wallet submits the transaction.
export { BrowserWallet, MeshTxBuilder } from "@meshsdk/core";
