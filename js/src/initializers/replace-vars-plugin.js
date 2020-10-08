import YoastReplaceVarPlugin from "../analysis/plugins/replacevar-plugin";

/**
 * Initializes the ReplaceVars plugin.
 *
 * @param {Object} store The wpData store.
 *
 * @returns {void}
 */
const initReplaceVarsPlugin = ( store ) => {
	window.YoastSEO.wp = {};
	window.YoastSEO.wp.replaceVarsPlugin = new YoastReplaceVarPlugin( {
		store,
		registerPlugin: window.YoastSEO.app.registerPlugin,
		registerModification: window.YoastSEO.app.registerModification,
		rawData: window.YoastSEO.app.rawData,
		pluginReloaded: window.YoastSEO.app.pluginReloaded,
	} );
};

export default initReplaceVarsPlugin;