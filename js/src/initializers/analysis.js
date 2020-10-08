import { dispatch as wpDispatch, select } from "@wordpress/data";
import { Paper } from "yoastseo";
import { refreshDelay } from "../analysis/constants";
import CustomAnalysisData from "../analysis/CustomAnalysisData";
import getApplyMarks from "../analysis/getApplyMarks";
import getContentLocale from "../analysis/getContentLocale";
import handleWorkerError from "../analysis/handleWorkerError";
import { sortResultsByIdentifier } from "../analysis/refreshAnalysis";
import { createAnalysisWorker } from "../analysis/worker";
import { debounce, merge } from "lodash";
import measureTextWidth from "../helpers/measureTextWidth";
import Pluggable from "../lib/Pluggable";

/**
 * Retrieves the data needed for the analyses.
 *
 * We use the following data sources:
 * 1. Store via wp.data's select.
 * 2. Custom data callbacks.
 * 3. Pluggable modifications.
 *
 * @param {CustomAnalysisData} customAnalysisData The custom analysis data.
 * @param {Pluggable}          pluggable          The Pluggable.
 *
 * @returns {Paper} The paper data used for the analyses.
 */
const collectAnalysisData = ( customAnalysisData, pluggable ) => {
	const {
		getEditorDataContent,
		getSearchMetadataKeyphrase,
	} = select( "yoast-seo/editor" );

	// Make a data structure for the paper data.
	const data = {
		text: getEditorDataContent(),
		keyword: getSearchMetadataKeyphrase(),
		synonyms: "",
		/*
		 * The analysis data is provided by the snippet editor. The snippet editor transforms the title and the
		 * description on change only. Therefore, we have to use the original data when the analysis data isn't
		 * available. This data is transformed by the replacevar plugin via pluggable.
		 */
		// description: storeData.analysisData.snippet.description || storeData.snippetEditor.data.description,
		// title: storeData.analysisData.snippet.title || storeData.snippetEditor.data.title,
		// url: storeData.snippetEditor.data.slug,
		// permalink: storeData.settings.snippetEditor.baseUrl + storeData.snippetEditor.data.slug,
	};

	merge( data, customAnalysisData.getData() );

	// Modify the data through pluggable.
	if ( pluggable.loaded ) {
		data.title = pluggable._applyModifications( "data_page_title", data.title );
		data.title = pluggable._applyModifications( "title", data.title );
		data.description = pluggable._applyModifications( "data_meta_desc", data.description );
		data.text = pluggable._applyModifications( "content", data.text );
	}

	data.titleWidth = measureTextWidth( data.title );
	data.locale = getContentLocale();

	return Paper.parse( data );
}

/**
 * Refreshes the analysis.
 *
 * @param {AnalysisWorkerWrapper} worker      The analysis worker to request the analysis from.
 * @param {Function}              collectData Function that collects the analysis data.
 * @param {Function}              applyMarks  Function that applies the marks in the content.
 *
 * @returns {void}
 */
const refreshAnalysis = ( worker, collectData, applyMarks ) => {
	const paper = collectData();
	const dispatch = wpDispatch( "yoast-seo/editor" );

	worker.analyze( paper )
		.then( ( { result: { seo, readability } } ) => {
			if ( seo ) {
				// Only update the main results, which are located under the empty string key.
				const seoResults = seo[ "" ];

				// Recreate the getMarker function after the worker is done.
				seoResults.results.forEach( result => {
					result.getMarker = () => () => applyMarks( paper, result.marks );
				} );

				seoResults.results = sortResultsByIdentifier( seoResults.results );

				dispatch.setSeoResultsForKeyword( paper.getKeyword(), seoResults.results );
				dispatch.setOverallSeoScore( seoResults.score, paper.getKeyword() );
				dispatch.refreshSnippetEditor();

				// dataCollector.saveScores( seoResults.score, paper.getKeyword() );
			}

			if ( readability ) {
				// Recreate the getMarker function after the worker is done.
				readability.results.forEach( result => {
					result.getMarker = () => () => applyMarks( paper, result.marks );
				} );

				readability.results = sortResultsByIdentifier( readability.results );

				dispatch.setReadabilityResults( readability.results );
				dispatch.setOverallReadabilityScore( readability.score );
				dispatch.refreshSnippetEditor();

				// dataCollector.saveContentScore( readability.score );
			}
		} )
		.catch( handleWorkerError );
};

/**
 * Initializes the analysis.
 *
 * @returns {void}
 */
const initAnalysis = () => {
	const customAnalysisData = new CustomAnalysisData();

	window.YoastSEO = window.YoastSEO || {};

	window.YoastSEO.app = {};

	// Initialize the pluggable in non-loaded form.
	window.YoastSEO.app.pluggable = {
		loaded: false,
	};

	window.YoastSEO.analysis = {};
	window.YoastSEO.analysis.worker = createAnalysisWorker();
	window.YoastSEO.analysis.applyMarks = ( paper, marks ) => getApplyMarks()( paper, marks );
	window.YoastSEO.app.refresh = debounce( () => refreshAnalysis(
		window.YoastSEO.analysis.worker,
		collectAnalysisData.bind( null, customAnalysisData, window.YoastSEO.app.pluggable ),
		window.YoastSEO.analysis.applyMarks,
	), refreshDelay );

	window.YoastSEO.app.registerCustomDataCallback = customAnalysisData.register;
	window.YoastSEO.app.pluggable = new Pluggable( window.YoastSEO.app.refresh );
	window.YoastSEO.app.registerPlugin = window.YoastSEO.app.pluggable._registerPlugin;
	window.YoastSEO.app.pluginReady = window.YoastSEO.app.pluggable._ready;
	window.YoastSEO.app.pluginReloaded = window.YoastSEO.app.pluggable._reloaded;
	window.YoastSEO.app.registerModification = window.YoastSEO.app.pluggable._registerModification;
	window.YoastSEO.app.changeAssessorOptions = function( assessorOptions ) {
		window.YoastSEO.analysis.worker.initialize( assessorOptions ).catch( handleWorkerError );
		window.YoastSEO.app.refresh();
	};
};

export default initAnalysis;
