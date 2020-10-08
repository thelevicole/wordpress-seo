import { pickBy } from "lodash";
import { combineReducers, registerStore } from "@wordpress/data";
import reducers from "../redux/reducers";
import * as selectors from "../redux/selectors";
import * as actions from "../redux/actions";
import { setSettings } from "../redux/actions/settings";
import { loadSearchMetadata, setSEMrushChangeCountry, setSEMrushLoginStatus } from "../redux/actions";
import * as contentAnalysisActions from "yoast-components/composites/Plugin/ContentAnalysis/actions/contentAnalysis";

/**
 * Initializes the Yoast SEO editor store.
 *
 * @returns {object} The Yoast SEO editor store.
 */
export default function initEditorStore() {
	const store = registerStore( "yoast-seo/editor", {
		reducer: combineReducers( reducers ),
		selectors,
		actions: pickBy( {
			...contentAnalysisActions,
			...actions,
		}, x => typeof x === "function" ),
	} );

	store.dispatch(
		setSettings( {
			socialPreviews: {
				sitewideImage: window.wpseoScriptData.metabox.sitewide_social_image,
				authorName: window.wpseoScriptData.metabox.author_name,
				siteName: window.wpseoScriptData.metabox.site_name,
				contentImage: window.wpseoScriptData.metabox.first_content_image,
				twitterCardType: window.wpseoScriptData.metabox.twitterCardType,
			},
			snippetEditor: {
				baseUrl: window.wpseoScriptData.metabox.base_url,
				date: window.wpseoScriptData.metabox.metaDescriptionDate,
				recommendedReplacementVariables: window.wpseoScriptData.analysis.plugins.replaceVars.recommended_replace_vars,
				siteIconUrl: window.wpseoScriptData.metabox.siteIconUrl,
			},
		} )
	);
	store.dispatch(
		setSEMrushChangeCountry( window.wpseoScriptData.metabox.countryCode )
	);
	store.dispatch(
		setSEMrushLoginStatus( window.wpseoScriptData.metabox.SEMrushLoginStatus )
	);
	store.dispatch( loadSearchMetadata() );

	return store;
}
