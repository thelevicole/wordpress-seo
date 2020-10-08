/* global jQuery */
import domReady from "@wordpress/dom-ready";
import ElementorEditorData from "./analysis/elementorEditorData";
import initAnalysis from "./initializers/analysis";
import initElementorEditorIntegration from "./initializers/elementor-editor-integration";
import initEditorStore from "./initializers/editor-store";
import initScraper from "./initializers/elementor-scraper";
import initAdminMedia from "./initializers/admin-media";
import initL10n from "./initializers/l10n";
import initElementorData from "./watchers/editorData/elementor";

domReady( () => {
	// Initialize the editor store.
	const store = initEditorStore();

	// Initialize the editor data watcher.
	initElementorData();

	// Initialize the editor data.
	const editorData = new ElementorEditorData( () => {
	}, store );
	editorData.initialize( window.wpseoScriptData.analysis.plugins.replaceVars.replace_vars );
	window.editorData = editorData;

	initL10n();
	initAnalysis();

	// Initialize the scraper.
	initScraper( jQuery, store, editorData );

	// Initialize the media library for our social settings.
	initAdminMedia( jQuery );
} );

// Initialize the editor integration.
initElementorEditorIntegration();


// Initialize the scraper.
initScraper( jQuery, store, editorData );

// Initialize the media library for our social settings.
initAdminMedia( jQuery );

// STORE INIT
// Register our store to WP data.
// Added:
// - editorData (title, description, slug)
//		Is slug really an editor data thing? Also, do we need the extra draft slug checks?
// - searchMetadata (seo title, seo description, focus keyphrase)

// WATCHER INIT
// Only for non-component data flow into the store.
// Added: editorData for Elementor

// PAGE INIT
// - Render React root.
// > PER COMPONENT INIT
//   Example: analysis: subscribe to store and refresh when data changed

// Create replacement variable initalization that replaces the `fillReplacementVariables` in the analysis data.
// Use the new searchMetadata.keyphrase in the KeywordInput component.
