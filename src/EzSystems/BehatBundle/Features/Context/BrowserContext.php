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
use Behat\Mink\Element\NodeElement;

/**
 * Browser interface helper context.
 */
class BrowserContext extends BaseFeatureContext
{
    /**
     * This will tell us which containers (design) to search, should be set by child classes.
     *
     * ex:
     * $mainAttributes = array(
     *      "content"   => "thisIsATag",
     *      "column"    => array( "class" => "thisIstheClassOfTheColumns" ),
     *      "menu"      => "//xpath/for/the[menu]",
     *      ...
     * );
     *
     * @var array This will have a ( identifier => array )
     */
    public $mainAttributes = array();

    /**
     * This method works as a complement to the $mainAttributes var
     *
     * @param  string $block This should be an identifier for the block to use
     *
     * @return string
     *
     * @see $this->mainAttributes
     */
    public function makeXpathForBlock( $block = 'main' )
    {
        if ( !isset( $this->mainAttributes[strtolower( $block )] ) )
            return "";

        $xpath = $this->mainAttributes[strtolower( $block )];

        // check if value is a composed array
        if ( is_array( $xpath ) )
        {
            $nuXpath = "";
            // verify if there is a tag
            if ( isset( $xpath['tag'] ) )
            {
                if ( strpos( $xpath, "/" ) === 0 || strpos( $xpath, "(" ) === 0 )
                    $nuXpath = $xpath['tag'];
                else
                    $nuXpath = "//" . $xpath['tag'];

                unset( $xpath['tag'] );
            }
            else
                $nuXpath = "//*";

            foreach ( $xpath as $key => $value )
            {
                switch ( $key ) {
                case "text":
                    $att = "text()";
                    break;
                default:
                    $att = "@$key";
                }
                $nuXpath .= "[contains($att, {$this->literal( $value )})]";
            }

            return $nuXpath;
        }

        //  if the string is an Xpath
        if ( strpos( $xpath, "/" ) === 0 || strpos( $xpath, "(" ) === 0  )
            return $xpath;

        // if xpath is an simple tag
        return "//$xpath";
    }

    /**
     * With this function we get a centralized way to define what are the possible
     * tags for a type of data and return them as a xpath search
     *
     * @param  string $type Type of text (ie: if header/title, or list element, ...)
     *
     * @return string Xpath string for searching elements insed those tags
     *
     * @throws PendingException If the $type isn't defined yet
     */
    public function getTagsFor( $type )
    {
        switch ( strtolower( $type ) ){
        case "topic":
        case "header":
        case "title":
            return array( "h1", "h2", "h3" );
        case "list":
            return array( "li" );
        }

        throw new PendingException( "Tag's for '$type' type not defined" );
    }

    /**
     * This should be seen as a complement to self::getTagsFor() where it will
     * get the respective tags from there and will make a valid Xpath string with
     * all OR's needed
     *
     * @param array  $tags  Array of tags strings (ex: array( "a", "p", "h3", "table" ) )
     * @param string $xpath String to be concatenated to each tag
     *
     * @return string
     */
    public function concatTagsWithXpath( array $tags, $xpath = null )
    {
        $finalXpath = "";
        for ( $i = 0; !empty( $tags[$i] ); $i++ )
        {
            $finalXpath .= "//{$tags[$i]}$xpath";
            if ( !empty($tags[$i + 1]) )
                $finalXpath .= " | ";
        }

        return $finalXpath;
    }

    /**
     * This is a simple shortcut for
     * $this->getSession()->getPage()->getSelectorsHandler()->xpathLiteral()
     *
     * @param string $text
     */
    public function literal( $text )
    {
        return $this->getSession()->getSelectorsHandler()->xpathLiteral( $text );
    }

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
     * @Then /^I see links:$/
     */
    public function iSeeLinks( TableNode $table )
    {
        $this->iSeeOnSomePlaceLinks( 'main', $table );
    }

    /**
     * @Then /^I see on ([A-Za-z\s]*) links:$/
     */
    public function iSeeOnSomePlaceLinks( $somePlace, TableNode $table )
    {
        $base = $this->makeXpathForBlock( $somePlace );
        // get all links
        $available = $this->getSession()->getPage()->findAll( "xpath", "$base//a[@href]" );

        $rows = $table->getRows();
        array_shift( $rows );   // this is needed to take the first row ( readability only )

        // remove links from embeded arrays
        $links = array();
        foreach ( $rows as $row )
            $links[] = $row[0];

        // and finaly verify their existence
    }

    /**
     * Check existence of links
     *
     * @param array         $links
     * @param NodeElement[] $available
     */
    protected function checkLinksExistence( array $links, array $available )
    {
        // verify if every required link is in available
        foreach ( $links as $link )
        {
            $name = $link;
            $url = str_replace( ' ', '-', $name );

            $i = 0;
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
            Assertion::assertNotNull( $test, "Couldn't find '$name' link" );
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
     * @Then /^I see table with:$/
     */
    public function iSeeTableWith( TableNode $table )
    {
        $rows = $table->getRows();
        $headers = array_shift( $rows );

        $max = count( $headers );
        $mainHeader = array_shift( $headers );
        foreach( $rows as $row )
        {
            $mainColumn = array_shift( $row );
            $foundRows = $this->getTableRow( $mainColumn, $mainHeader );

            $found = false;
            $maxFound = count( $foundRows );
            for ( $i = 0; $i < $maxFound && !$found; $i++ )
                if( $this->assertTableRow( $foundRows[$i], $row, $headers ) )
                    $found = true;

            $message = "Couldn't find row with elements: '" . implode( ",", array_merge( array($mainColumn), $row ) ) . "'";
            Assertion::assertTrue( $found, $message );
        }
    }

    /**
     * Verifies if a row as the expected columns, position of columns can be added
     * for a more accurated assertion
     *
     * @param \Behat\Mink\Element\NodeElement  $row              Table row node element
     * @param string[]                         $columns          Column text to assert
     * @param string[]|int[]                   $columnsPositions Columns positions in int or string (number must be in string)
     *
     * @return boolean
     */
    protected function assertTableRow( NodeElement $row, array $columns, array $columnsPositions = null )
    {
        // find which kind of column is in this row
        $elType = $row->find( 'xpath', "/th" );
        $type = ( empty( $elType ) ) ? '/td': '/th';

        $max = count( $columns );
        for( $i = 0; $i < $max; $i++ )
        {
            $position = "";
            if( !empty( $columnsPositions[$i] ) )
                $position = "[{$this->getNumberFromString( $columnsPositions[$i] )}]";

            $el = $row->find( "xpath", "$type$position" );

            // check if match with expected if not return false
            if ( $el === null || $columns[$i] !== $el->getText())
                return false;
        }

        // if we're here then it means all have ran as expected
        return true;
    }

    /**
     * Find a(all) table row(s) that match the column text
     *
     * @param string        $text       Text to be found
     * @param string|int    $column     In which column the text should be found
     * @param string        $tableXpath If there is a specific table
     *
     * @return Behat\Mink\Element\NodeElement[]
     */
    protected function getTableRow( $text, $column = null, $tableXpath = null )
    {
         // check column
        if ( !empty( $column ) )
        {
            if ( is_integer( $column ) )
                $columnNumber = "[$column]";
            else
                $columnNumber = "[{$this->getNumberFromString( $column )}]";
        }
        else
            $columnNumber = "";

        // get all possible elements
        $elements = array_merge(
            $this->getSession()->getPage()->findAll( "xpath", "$tableXpath//tr/th" ),
            $this->getSession()->getPage()->findAll( "xpath", "$tableXpath//tr/td" )
        );

        $foundXpath = array();
        $total = count( $elements );
        $i = 0;
        while ( $i < $total )
        {
            if(strpos( $elements[$i]->getText(), $text ) !== false )
                $foundXpath[] = $elements[$i]->getParent();

            $i++;
        }

        return $foundXpath;
    }

    /**
     * @Then /^I see "([^"]*)" text emphasized$/
     */
    public function iSeeTextEmphasized( $text )
    {
        $this->iSeeOnSomePlaceTextEmphasized( 'main', $text );
    }

    /**
     * @Then /^I see (?:on|at) "([^"]*)" (?:the |)"([^"]*)" text emphasized$/
     */
    public function iSeeOnSomePlaceTextEmphasized( $somePlace, $text )
    {
        // first find the text
        $base = $this->makeXpathForBlock( $somePlace );
        $el = $this->getSession()->getPage()->findAll( "xpath", "$base//*[contains( text(), {$this->literal( $text )} )]" );
        Assertion::assertNotNull( $el, "Coudn't find text '$text' at '$somePlace' content" );

        // verify only one was found
        Assertion::assertEquals( count( $el ), 1, "Expecting to find '1' found '" . count( $el ) ."'" );

        // finaly verify if it has custom charecteristics
        Assertion::assertTrue(
            $this->assertElementEmphasized( $el[0] ),
            "The text '$text' isn't emphasized"
        );
    }

    /**
     * Verifies if the element has 'special' configuration on a attribute (default -> style)
     *
     * @param \Behat\Mink\Element\NodeElement  $el              The element that we want to test
     * @param string                           $characteristic  Verify a specific characteristic from attribute
     * @param string                           $attribute       Verify a specific attribute
     *
     * @return boolean
     */
    protected function assertElementEmphasized( NodeElement $el, $characteristic = null, $attribute = "style" )
    {
        // verify it has the attribute we're looking for
        if ( !$el->hasAttribute( $attribute ) )
            return false;

        // get the attribute
        $attr = $el->getAttribute( $attribute );

        // check if want to test specific characteristic and if it is present
        if ( !empty( $characteristic) && strpos( $attr, $characteristic ) === false )
            return false;

        // if we're here it is emphasized
        return true;
    }

    /**
     * @Then /^(?:|I )see ["'](.+)["'] (?:title|topic)$/
     */
    public function iSeeTitle( $title )
    {
        $literal = $this->literal( $title );
        $xpath = $this->concatTagsWithXpath(
            $this->getTagsFor( "title" ),
            "[text() = $literal]"
        );

        $el = $this->getSession()->getPage()->find( "xpath", $xpath );

        // assert that message was found
        Assertion::assertNotNull( $el, "Could not find '$title' title." );
        Assertion::assertContains(
            $title,
            $el->getText(),
            "Couldn't find '$title' title in '{$el->getText()}'"
        );
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
