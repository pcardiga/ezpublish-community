<?php
/**
 * File containing the BrowserContext class.
 *
 * This class contains general feature context for Behat.
 *
 * @copyright Copyright (C) 1999-2013 eZ Systems AS. All rights reserved.
 * @license http://www.gnu.org/licenses/gpl-2.0.txt GNU General Public License v2
 * @version //autogentag//
 */

namespace EzSystems\BehatBundle\Features\Context;

use EzSystems\BehatBundle\Features\Context\FeatureContext as BaseFeatureContext;
use PHPUnit_Framework_Assert as Assertion;
use Behat\Behat\Context\Step;
use Behat\Gherkin\Node\TableNode;
use Behat\Behat\Exception\PendingException;
use Behat\Mink\Exception\UnsupportedDriverActionException as MinkUnsupportedDriverActionException;

/**
 * Browser interface helper context.
 */
class BrowserContext extends BaseFeatureContext
{
    /**
     * @Given /^(?:|I )am logged in as "([^"]*)" with password "([^"]*)"$/
     */
    public function iAmLoggedInAsWithPassword( $user, $password )
    {
        return array(
            new Step\Given( 'I am on "/user/login"' ),
            new Step\When( 'I fill in "Username" with "' . $user . '"' ),
            new Step\When( 'I fill in "Password" with "' . $password . '"' ),
            new Step\When( 'I press "Login"' ),
            new Step\Then( 'I should be redirected to "/"' ),
        );
    }

    /**
     * @Then /^(?:|I )am (?:at|on) the "([^"]*)(?:| page)"$/
     * @Then /^(?:|I )see "([^"]*)" page$/
     */
    public function iAmOnThe( $pageIdentifier )
    {
        $currentUrl = $this->getUrlWithoutQueryString( $this->getSession()->getCurrentUrl() );

        $expectedUrl = $this->locatePath( $this->getPathByPageIdentifier( $pageIdentifier ) );

        Assertion::assertEquals(
            $expectedUrl,
            $currentUrl,
            "Unexpected URL of the current site. Expected: '$expectedUrl'. Actual: '$currentUrl'."
        );
    }

    /**
     * @Given /^(?:|I )click (?:on|at) "([^"]*)" link$/
     *
     * Can also be used @When steps
     */
    public function iClickAtLink( $link )
    {
        return array(
            new Step\When( "I follow \"{$link}\"" )
        );
    }

    /**
     * @Then /^(?:|I )(?:don\'t|do not) see links(?:|\:)$/
     */
    public function iDonTSeeLinks( TableNode $table )
    {
        $this->iDonTSeeOnSomePlaceTheLinks( 'main', $table );
    }

    /**
     * @Given /^I (?:don\'t|do not) see on ([A-Za-z\s]*) the links:$/
     */
    public function iDonTSeeOnSomePlaceTheLinks( $somePlace, TableNode $table )
    {
        $rows = $table->getRows();
        array_shift( $rows );   // this is needed to take the first row ( readability only )

        $base = $this->makeXpathForBlock( $somePlace );
        foreach ( $rows as $row )
        {
            $link = $row[0];
            $literal = $this->literal( $link );
            $el = $this->getSession()->getPage()->find( "xpath", "$base//a[text() = $literal][@href]" );

            Assertion::assertNull( $el, "Unexpected link found" );
        }
    }

    /**
     * @Then /^I (?:don\'t|do not) see(?: the| ) ([A-Za-z\s]*) menu$/
     */
    public function iDonTSeeTheSubMenu( $menu )
    {
        $xpath = $this->makeXpathForBlock( "$menu menu" );
        if ( empty( $xpath ) )
            throw new PendingException( "Menu '$menu' not defined" );

        $el = $this->getSession()->getPage()->find( "xpath", $xpath );

        Assertion::assertNull( $el, "" );
    }

    /**
     * @Given /^(?:|I )am (?:at|on) (?:|the )"([^"]*)" page$/
     * @When  /^(?:|I )go to (?:|the )"([^"]*)"(?:| page)$/
     */
    public function iGoToThe( $pageIdentifier )
    {
        return array(
            new Step\When( 'I am on "' . $this->getPathByPageIdentifier( $pageIdentifier ) . '"' ),
        );
    }

    /**
     * @When /^(?:|I )search for "([^"]*)"$/
     */
    public function iSearchFor( $searchPhrase )
    {
        $session = $this->getSession();
        $searchField = $session->getPage()->findById( 'site-wide-search-field' );

        Assertion::assertNotNull( $searchField, 'Search field not found.' );

        $searchField->setValue( $searchPhrase );

        // Ideally, using keyPress(), but doesn't work since no keypress handler exists
        // http://sahi.co.in/forums/discussion/2717/keypress-in-java/p1
        //     $searchField->keyPress( 13 );
        //
        // Using JS instead:
        // Note:
        //     $session->executeScript( "$('#site-wide-search').submit();" );
        // Gives:
        //     error:_call($('#site-wide-search').submit();)
        //     SyntaxError: missing ) after argument list
        //     Sahi.ex@http://<hostname>/_s_/spr/concat.js:3480
        //     @http://<hostname>/_s_/spr/concat.js:3267
        // Solution: Encapsulating code in a closure.
        // @todo submit support where recently added to MinkCoreDriver, should us it when the drivers we use support it
        try
        {
            $session->executeScript( "(function(){ $('#site-wide-search').submit(); })()" );
        }
        catch ( MinkUnsupportedDriverActionException $e )
        {
            // For drivers not able to do javascript we assume we can click the hidden button
            $searchField->getParent()->findButton( 'SearchButton' )->click();
        }

        // Store for reuse in result page
        $this->priorSearchPhrase = $searchPhrase;
    }

    /**
     * @Then /^(?:|I )see search (\d+) result$/
     */
    public function iSeeSearchResults( $arg1 )
    {
        $resultCountElement = $this->getSession()->getPage()->find( 'css', 'div.feedback' );

        Assertion::assertNotNull(
            $resultCountElement,
            'Could not find result count text element.'
        );

        Assertion::assertEquals(
            "Search for \"{$this->priorSearchPhrase}\" returned {$arg1} matches",
            $resultCountElement->getText()
        );
    }

    /**
     * @Then /^(?:|I )see links for Content objects(?:|\:)$/
     */
    public function iSeeLinksForContentObjects( TableNode $table )
    {
        $this->iSeeOnSomePlaceTheLinksForContentObjects( 'main', $table );
    }

    /**
     * @Given /^I see on ([A-Za-z\s]*) the links for Content objects(?:|\:)$/
     *
     * @todo check the parents (if defined)
     */
    public function iSeeOnSomePlaceTheLinksForContentObjects( $somePlace, TableNode $table)
    {
        $rows = $table->getRows();
        array_shift( $rows );   // this is needed to take the first row ( readability only )

        $links = $parents = array();
        foreach ( $rows as $row )
        {
            if( count( $row ) >= 2 )
                list( $links[], $parents[] ) = $row;
            else
                $links[] = $row[0];
        }

        // check links
        $this->checkLinksForContentObjects( $links, $somePlace );

        // to end the assertion, we need to verify parents (if specified)
//        if ( !empty( $parents ) )
//            $this->checkLinkParents( $links, $parents );
    }

    /**
     * Find the links passed, assert they exist in the specified place
     *
     * @param array  $links The links to be asserted
     * @param string $where The place where should search for the links
     *
     * @todo verify if the links are for objects
     * @todo check if it has a different url alias
     */
    protected function checkLinksForContentObjects( array $links, $where )
    {
        $base = $this->makeXpathForBlock( $where );
        foreach ( $links as $link )
        {
            Assertion::assertNotNull( $link, "Missing link for searching on table" );

            $literal = $this->literal( $link );
            $el = $this->getSession()->getPage()->find(
                "xpath",
                "$base//a[contains( text(),$literal )][@href]"
            );

            Assertion::assertNotNull( $el, "Couldn't find a link for object '$link'" );
        }
    }

    /**
     * @Then /^(?:|I )see links for Content objects in following order(?:|\:)$/
     */
    public function iSeeLinksForContentObjectsInFollowingOrder( TableNode $table )
    {
        $this->iSeeOnSomePlaceLinksInFollowingOrder( 'main', $table );
    }

    /**
     * @Then /^I see on ([A-Za-z\s]*) links in following order:$/
     *
     *  @todo check "parent" node
     */
    public function iSeeOnSomePlaceLinksInFollowingOrder( $somePlace, TableNode $table )
    {
        $base = $this->makeXpathForBlock( $somePlace );
        // get all links
        $available = $this->getSession()->getPage()->findAll( "xpath", "$base//a[@href]" );

        $rows = $table->getRows();
        array_shift( $rows );   // this is needed to take the first row ( readability only )

        // make link and parent arrays:
        $links = $parents = array();
        foreach ( $rows as $row )
        {
            if ( count( $row ) >= 2 )
                list( $links[], $parents[] ) = $row;
            else
                $links[] = $row[0];
        }

        // now verify the link order
        $this->checkLinkOrder( $links, $available );

        // to end the assertion, we need to verify parents (if specified)
//        if ( !empty( $parents ) )
//            $this->checkLinkParents( $links, $parents );
    }

    /**
     * Checks if links show up in the following order
     * Notice: if there are 3 links and we omit the middle link it will also be
     *  correct. It only checks order, not if there should be anything in
     *  between them
     *
     * @param array         $links
     * @param NodeElement[] $available
     */
    protected function checkLinkOrder( array $links, array $available )
    {
        $i = $passed = 0;
        $last = '';
        foreach ( $links as $link )
        {
            $name = $link;
            $url = str_replace( ' ', '-', $name );

            // find the object
            while(
                !empty( $available[$i] )
                && strpos( $available[$i]->getAttribute( "href" ), $url ) === false
                && strpos( $available[$i]->getText(), $name ) === false
            )
                $i++;

            $test = !null;
            if( empty( $available[$i] ) )
                $test = null;

            // check if the link was found or the $i >= $count
            Assertion::assertNotNull( $test, "Couldn't find '$name' after '$last'" );

            $passed++;
            $last = $name;
        }

        Assertion::assertEquals(
            count( $links ),
            $passed,
            "Expected to evaluate '{count( $links )}' links evaluated '{$passed}'"
        );
    }

    /**
     * @Then /^(?:|I )see links in(?:|\:)$/
     */
    public function iSeeLinksIn( TableNode $table )
    {
        $session = $this->getSession();
        $rows = $table->getRows();
        array_shift( $rows );   // this is needed to take the first row ( readability only )
        foreach ( $rows as $row )
        {
            // prepare data
            Assertion::assertEquals( count( $row ), 2, "The table should be have array with link and tag" );
            list( $link, $type ) = $row;

            // make xpath
            $literal = $this->literal( $link );
            $xpath = $this->concatTagsWithXpath(
                $this->getTagsFor( $type ),
                "//a[@href and text() = $literal]"
            );

            $el = $session->getPage()->find( "xpath", $xpath );

            Assertion::assertNotNull( $el, "Couldn't find a link with '$link' text" );
        }
    }

    /**
     * @Then /^(?:|I )see (\d+) "([^"]*)" elements listed$/
     */
    public function iSeeListedElements( $count, $objectType )
    {
        $objectListTable = $this->getSession()->getPage()->find(
            'xpath',
            '//table[../h1 = "' . $objectType  . ' list"]'
        );

        Assertion::assertNotNull(
            $objectListTable,
            'Could not find listing table for ' . $objectType
        );

        Assertion::assertCount(
            $count + 1,
            $objectListTable->findAll( 'css', 'tr' ),
            'Found incorrect number of table rows.'
        );
    }

    /**
     * @Then /^I see (?:the |)([A-Za-z\s]*) menu$/
     */
    public function iSeeSomeMenu( $menu )
    {
        $xpath = $this->makeXpathForBlock( "$menu menu" );
        if ( empty( $xpath ) )
            throw new PendingException( "Menu '$menu' not defined" );

        $el = $this->getSession()->getPage()->find( "xpath", $xpath );

        Assertion::assertNotNull( $el, "" );
    }

    /**
     * @Then /^(?:|I )should be redirected to "([^"]*)"$/
     */
    public function iShouldBeRedirectedTo( $redirectTarget )
    {
        $redirectForm = $this->getSession()->getPage()->find( 'css', 'form[name="Redirect"]' );

        Assertion::assertNotNull(
            $redirectForm,
            'Missing redirect form.'
        );

        Assertion::assertEquals( $redirectTarget, $redirectForm->getAttribute( 'action' ) );
    }

    /**
     * @Then /^(?:|I )want dump of (?:|the )page$/
     */
    public function iWantDumpOfThePage()
    {
        echo $this->getSession()->getPage()->getContent();
    }
}
