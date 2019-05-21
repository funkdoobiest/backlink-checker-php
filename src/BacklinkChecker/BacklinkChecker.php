<?php

namespace Valitov\BacklinkChecker;

use KubAT\PhpSimple\HtmlDomParser;

/**
 * Class BacklinkChecker
 * Abstract class for checking the backlinks
 * @package Valitov\BacklinkChecker
 * @author Ramil Valitov ramilvalitov@gmail.com
 */
abstract class BacklinkChecker
{
    /**
     * @param string $html
     * @param string $pattern
     * @param bool $scanLinks
     * @param bool $scanImages
     * @return Backlink[]
     * @throws \InvalidArgumentException
     * @throws \RuntimeException
     */
    protected function getRawBacklink($html, $pattern, $scanLinks, $scanImages)
    {
        $result = array();

        if (!is_string($html) || !is_string($pattern))
            throw new \InvalidArgumentException("Argument must be string");
        if (@preg_match($pattern, null) === false)
            throw new \InvalidArgumentException("Invalid pattern. Check the RegExp syntax.");
        if (strlen($html) <= 0 || strlen($pattern) <= 0)
            return $result;
        $dom = HtmlDomParser::str_get_html($html);
        if ($dom === false || $dom === null)
            throw new \RuntimeException("Failed to parse HTML");

        if ($scanLinks) {
            //Searching <a> tags
            $list = $dom->find("a[href]");
            if (is_array($list)) {
                foreach ($list as $link) {
                    if (isset($link->href) && preg_match($pattern, $link->href) === 1) {
                        //We found a matching backlink
                        $contents = html_entity_decode(trim($link->plaintext));
                        $target = isset($link->_target) ? $link->_target : "";
                        $noFollow = false;
                        if (isset($link->rel)) {
                            $relList = explode(" ", $link->rel);
                            if (is_array($relList)) {
                                foreach ($relList as $item) {
                                    /** @noinspection SpellCheckingInspection */
                                    if (strtolower(trim($item)) === "nofollow")
                                        $noFollow = true;
                                }
                            }
                        }
                        array_push($result, new Backlink($link->href, $contents, $noFollow, $target, "a"));
                    }
                }
            }
        }

        if ($scanImages) {
            //Searching <img> tags - image hotlinking
            $list = $dom->find("img[src]");
            if (is_array($list)) {
                foreach ($list as $link) {
                    if (isset($link->src) && preg_match($pattern, $link->src) === 1) {
                        //We found a matching backlink
                        $contents = isset($link->alt) ? html_entity_decode(trim($link->alt)) : "";
                        array_push($result, new Backlink($link->src, $contents, false, "", "img"));
                    }
                }
            }
        }
        $dom->clear();
        return $result;
    }

    /**
     * @param string $url
     * @param boolean $makeScreenshot
     * @return HttpResponse
     * @throws \InvalidArgumentException
     * @throws \RuntimeException
     */
    abstract protected function browsePage($url, $makeScreenshot);

    /**
     * @param string $url
     * @param string $pattern
     * @param bool $scanLinks
     * @param bool $scanImages
     * @param boolean $makeScreenshot
     * @return BacklinkData
     */
    public function getBacklinks($url, $pattern, $scanLinks = true, $scanImages = false, $makeScreenshot = false)
    {
        $response = $this->browsePage($url, $makeScreenshot);

        if (!$response->getSuccess())
            $backlinks = [];
        else
            $backlinks = $this->getRawBacklink($response->getResponse(), $pattern, $scanLinks, $scanImages);
        return new BacklinkData($response, $backlinks);
    }
}