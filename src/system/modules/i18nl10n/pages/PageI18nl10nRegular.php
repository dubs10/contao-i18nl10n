<?php
/**
 * i18nl10n Contao Module
 *
 * The i18nl10n module for Contao allows you to manage multilingual content
 * on the element level rather than with page trees.
 *
 *
 * @copyright   2015 Verstärker, Patric Eberle
 * @author      Patric Eberle <line-in@derverstaerker.ch>
 * @package     i18nl10n
 * @license     LGPLv3 http://www.gnu.org/licenses/lgpl-3.0.html
 */

namespace Verstaerker\I18nl10n\Pages;

use Verstaerker\I18nl10n\Classes\I18nl10n;


/**
 * Class I18nPageRegular
 *
 * @copyright   2015 Verstärker, Patric Eberle
 * @author      Patric Eberle <line-in@derverstaerker.ch>
 * @package     i18nl10n
 */
class PageI18nl10nRegular extends \PageRegular
{
    /**
     * Generate FE page
     * Override TL_PTY.regular
     *
     * @param      $objPage
     * @param bool $blnCheckRequest
     */
    function generate($objPage, $blnCheckRequest = false)
    {

        self::fixupCurrentLanguage();

        $arrLanguages = I18nl10n::getLanguagesByDomain();

        if ($GLOBALS['TL_LANGUAGE'] === $arrLanguages['default']) {
            // if default language is not published, give error
            if (!$objPage->l10n_published) {
                $objError = new $GLOBALS['TL_PTY']['error_404']();
                $objError->generate($objPage->id);
            }
            parent::generate($objPage);

            return;
        }

        $objPage = I18nl10n::findPublishedL10nPage($objPage, $GLOBALS['TL_LANGUAGE']);

        if (!$objPage) {
            // if fallback is not published, show 404
            if (!$objPage->l10n_published) {
                $objError = new $GLOBALS['TL_PTY']['error_404']();
                $objError->generate($objPage->id);

                parent::generate($objPage);
                return;
            } else {
                // else at least keep current language to prevent language change and set flag
                $objPage->language            = $GLOBALS['TL_LANGUAGE'];
                $objPage->useFallbackLanguage = true;
            }

        }

        // update root information
        $objL10nRootPage = self::getL10nRootPage($objPage);

        if ($objL10nRootPage) {
            $objPage->rootTitle = $objL10nRootPage->title;

            if ($objPage->pid == $objPage->rootId) {
                $objPage->parentTitle     = $objL10nRootPage->title;
                $objPage->parentPageTitle = $objL10nRootPage->pageTitle;
            }
        }

        parent::generate($objPage, $blnCheckRequest);
    }

    /**
     * Fix up current language depending on momentary user preference.
     *
     * Strangely $GLOBALS['TL_LANGUAGE'] is switched to the current user language if user is just
     * authenticating and has the language property set.
     * See system/libraries/User.php:202
     * We override this behavior and let the user temporarily use the selected by him language.
     * One workaround would be to not let the members have a language property.
     * Then this method will not be needed any more.
     */
    private function fixupCurrentLanguage()
    {
        // Try to get language from post (committed by language select) or get
        $selectedLanguage = \Input::post('language') ?: \Input::get('language');

        // If selected language is found already, use it
        if ($selectedLanguage) {
            $_SESSION['TL_LANGUAGE'] = $GLOBALS['TL_LANGUAGE'] = $selectedLanguage;
            return;
        }

        // if language is part of alias
        if (\Config::get('i18nl10n_urlParam') === 'alias') {
            $this->import('Environment');
            $environment  = $this->Environment;
            $strUrlSuffix = preg_quote(\Config::get('urlSuffix'));

            $regex = "@.*?\.([a-z]{2})$strUrlSuffix@";

            // only set language if found in url
            if (preg_match($regex, $environment->requestUri)) {
                $_SESSION['TL_LANGUAGE'] =
                $GLOBALS['TL_LANGUAGE'] = preg_replace($regex, '$1', $environment->requestUri);
                return;
            }
        }

        // If everything failed yet use session language
        $GLOBALS['TL_LANGUAGE'] = $_SESSION['TL_LANGUAGE'];
    }

    /**
     * Get localized root page by page object
     *
     * @param $objPage
     *
     * @return \Database\Result|null
     */
    public function getL10nRootPage($objPage)
    {
        $sql = "
            SELECT title
            FROM tl_page_i18nl10n
            WHERE
              pid = ?
              AND language = ?
        ";

        if (!BE_USER_LOGGED_IN) {
            $time = time();
            $sql .= "
                AND (start = '' OR start < $time)
                AND (stop = '' OR stop > $time)
                AND l10n_published = 1
            ";
        }

        $objL10nRootPage = \Database::getInstance()
            ->prepare($sql)
            ->limit(1)
            ->execute($objPage->rootId, $GLOBALS['TL_LANGUAGE']);

        return $objL10nRootPage->row() ? $objL10nRootPage : null;
    }
}
