import { setWordPressSeoL10n, setYoastComponentsL10n } from "../helpers/i18n";

/**
 * Initializes the l10n.
 *
 * @returns {void}
 */
const initL10n = () => {
	setYoastComponentsL10n();
	setWordPressSeoL10n();
};

export default initL10n;
