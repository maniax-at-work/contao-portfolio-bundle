<?php

/*
 * This file is part of [package name].
 *
 * (c) John Doe
 *
 * @license LGPL-3.0-or-later
 */

namespace maniax-at-work\ContaoPortfolioBundle\Tests;

use maniax-at-work\ContaoPortfolioBundle\ContaoPortfolioBundle;
use PHPUnit\Framework\TestCase;

class ContaoPortfolioBundleTest extends TestCase
{
    public function testCanBeInstantiated()
    {
        $bundle = new ContaoPortfolioBundle();

        $this->assertInstanceOf('maniax-at-work\ContaoPortfolioBundle\ContaoPortfolioBundle', $bundle);
    }
}
