/* global jQuery */
import domReady from "@wordpress/dom-ready";
import ElementorEditorData from "./analysis/elementorEditorData";
import initElementorEditorIntegration from "./initializers/elementor-editor-integration";
import initEditorStore from "./initializers/editor-store";
import initScraper from "./initializers/elementor-scraper";
import initAdminMedia from "./initializers/admin-media";
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
