<?php

namespace JWeiland\Events2\ViewHelpers;

/*
 * This file is part of the events2 project.
 *
 * It is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License, either version 2
 * of the License, or any later version.
 *
 * For the full copyright and license information, please read the
 * LICENSE.txt file that was distributed with this source code.
 *
 * The TYPO3 project - inspiring people to share!
 */
use TYPO3Fluid\Fluid\Core\Rendering\RenderingContextInterface;
use TYPO3Fluid\Fluid\Core\ViewHelper\AbstractViewHelper;
use TYPO3Fluid\Fluid\Core\ViewHelper\Traits\CompileWithRenderStatic;

/**
 * This VH checks if given URI is a valid YouTube-Link
 * and returns a special YouTube embed URI.
 */
class CreateYoutubeUriViewHelper extends AbstractViewHelper
{
    use CompileWithRenderStatic;

    /**
     * Initialize all arguments. You need to override this method and call
     * $this->registerArgument(...) inside this method, to register all your arguments.
     *
     * @return void
     */
    public function initializeArguments()
    {
        $this->registerArgument('link', 'string', 'Insert the YouTube-Link', true);
    }

    /**
     * Extract the youtube ID from link and return the embedded youtube url.
     *
     * @param array $arguments
     * @param \Closure $childClosure
     * @param RenderingContextInterface $renderingContext
     * @return string
     */
    public static function renderStatic(array $arguments, \Closure $childClosure, RenderingContextInterface $renderingContext)
    {
        if (preg_match('~^(|http:|https:)//(|www.)youtu(\.be|be)(.*?)(v=|embed/|)([a-zA-Z0-9_-]+)$~i', $arguments['link'], $matches)) {
            $url = '//www.youtube.com/embed/' . $matches[6];
        } else {
            $url = '//www.youtube.com/embed/' . $arguments['link'];
        }

        return $url;
    }
}
