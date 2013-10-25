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
     * Initializes context with parameters from behat.yml.
     *
     * @param array $parameters
     */
    public function __construct( array $parameters )
    {
        parent::__construct( $parameters );
        $this->pageIdentifierMap += array( "home" => "/" );
    }

    /**
     * Fill form will help to reduce the work for the tests where we got to fill
     * every field (or simply some fields if want to test a (some) specific
     * fields) without the need to specify every field
     * @param array             $formData   This is the array data in $this->form["Context]["SpecificForm"]
     * @param null|string|array $onlyListed Specifies the fields to be filled
     *      null   = all fields
     *      string = the only field
     *      array  = list of the fields
     * @return \Behat\Behat\Context\Step\When[]
     */
    public function fillForm( $formData, $onlyListed = null )
    {
        Assertion::assertNotNull( $formData, "Data for '$formData' form is empty" );

        $newSteps = array();
        // create the new steps When for adding the data to the form
        foreach ( $formData as $field => $value )
        {
            // verify if this field is to be filled
            if (
                empty( $onlyListed )                // all fields
                || $field === $onlyListed           // only specified field
                || in_array( $field, $onlyListed )  // fields in list
            )
            {
                // verify it is single value
                if ( !is_array( $field ) )
                    $newSteps[] = new Step\When( 'I fill "' . $field . '" with "' . $value . '"' );
                // else create Gherkin Table
                else {
                    $fields = "|";
                    foreach ( $value as $item )
                    {
                        // verify if its a single row table
                        if ( !is_array( $item ) )
                            $fields .= " $item |";
                        // if its a multirow table create rows
                        else {
                            // fill row
                            foreach ( $item as $last )
                                $fields .= " $last |";

                            $fields .= "\n|";
                        }
                    }
                }
            }
        }

        return $newSteps;
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
     * @Then /^(?:|I )am (?:at|on) the ([A-Za-z_-\d\s]*)page$/
     * @Then /^(?:|I )see ([A-Za-z_-\d\s]*[^\s])(?:| )page$/
     * @Then /^(?:|I )see "([^"]*)" page$/
     *
     * All following sentences will be caught by this:
     *      <sentence>                            => <$pageIdentifier>
     *      I am on the homepage                  => "home"
     *      see omg is this a real page           => "omg is this a real"
     *      I see "really 3#£ weird symb*l" page  => "really 3#£ weird symb*l"
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
     * @Given /^I attach an image$/
     */
    public function iAttachAnImage()
    {
        $image = $this->getDummyContentFor( 'image' );

        return new Step\When( 'I fill "image" with "' . $image . '"' );
    }

    /**
     * @When /^(?:|I )attach an image "([^"]*)" to "([^"]*)"$/
     */
    public function iAttachAnImageTo( $identifier, $field )
    {
        // check/get image for testing
        if ( !is_file( $identifier ) )
            $image = $this->getDummyContentFor( 'image' );
        else
            $image = $identifier;

        $this->contentHolder[$identifier] = $image;

        return new Step\When( 'I fill "' . $field . '" with "' . $image . '"' );
    }

    /**
     * @When /^(?:|I )click at "([^"]*)" button$/
     * @When /^(?:|I )click at ([A-Za-z_-\d\s]*) button$/
     */
    public function iClickAtButton( $button )
    {
        $el = $this->getSession()->getPage()->findButton( $button );

        // assert that button was found
        Assertion::assertNotNull(
            $el,
            "Could not find '$button' button."
        );

        $el->click();
    }

    /**
     * @Given /^(?:|I )click at "([^"]*)" link$/
     * @When  /^(?:|I )click at ([A-Za-z_-\d\s]*) link$/
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
     * @Then /^(?:|I )don\'t see "([^"]*)" button$/
     */
    public function iDonTSeeButton( $button )
    {
        $el = $this->getSession()->getPage()->findButton( $button );

        if ( !empty( $el ) )
            Assertion::assertNotEquals(
                $button,
                $el->getText(),
                "Unexpected '$button' button is present"
            );
        else
            Assertion::assertNull( $el );
    }

    /**
     * @Then /^(?:|I )don\'t see image "([^"]*)"$/
     */
    public function iDonTSeeImage( $image )
    {
        $expected = $this->getFileByIdentifier( $image );

        // iterate through all images checking
        foreach ( $this->getSession()->getPage()->findAll( 'xpath', '//img[@src]' ) as $img )
        {
            $path = $this->locatePath( $img->getAttribute( "src" ) );
            Assertion::assertNotEquals(
                md5_file( $expected ),
                md5_file( $path ),
                "Unexpected '$image' image found"
            );
        }
    }

    /**
     * @Then /^(?:|I )don\'t see links(?:|\:)$/
     */
    public function iDonTSeeLinks( TableNode $table )
    {
        $session = $this->getSession();
        $rows = $table->getRows();
        array_shift( $rows );   // this is needed to take the first row ( readability only )
        $base = $this->makeXpathForBlock( 'main' );
        foreach ( $rows as $row )
        {
            $link = $row[0];
            $url = $this->literal( str_replace( ' ', '-', $link ) );
            $literal = $this->literal( $link );
            $el = $session->getPage()->find( "xpath", "$base//a[text() = $literal][@href]" );

            Assertion::assertNull( $el, "Unexpected link found" );
        }
    }

    /**
     * @When /^(?:|I )fill a valid ([A-Za-z\s]+) form$/
     */
    public function iFillAValidForm( $form )
    {
        return $this->fillForm( $this->getFormData( $form ) );
    }

    /**
     * @When /^(?:|I )fill ([A-Za-z\s]+) form with(?:|\:)$/
     */
    public function iFillFormWith( $form, TableNode $table )
    {
        $formData = $this->getFormData( $form );

        $data = $this->convertTableToArrayOfData( $table, $formData );

        // fill the form
        return $this->fillForm( $data );
    }

    /**
     * @When /^(?:|I )only fill(?:| the) form with(?:|\:)$/
     */
    public function iOnlyFillFormWith( TableNode $table )
    {
        $data = $this->convertTableToArrayOfData( $table );

        // fill the form
        return $this->fillForm( $data );
    }


    /**
     * @When /^(?:|I )fill "([^"]*)" with "([^"]*)"$/
     *         I fill <field> with <value>
     *
     * @todo Find a way to treat selection
     * @todo Find a way to treat checkboxes
     * @todo Find a way to treat multiple data ( ex: author, multi option ,... )
     * @todo Find a way to treat with not specified values (ex: "A")
     */
    public function iFillWith( $field, $value )
    {
        // get page
        $page = $this->getSession()->getPage();

        $fieldElement = $page->find( 'xpath', "//input[contains( @id, '$field' )]" );

        // assert that the field was found
        Assertion::assertNotNull(
            $fieldElement,
            "Could not find '$field' field."
        );

        // check if data is in fact an identifier (alias)
        if ( $this->isSingleCharacterIdentifier( $value ) )
            $value = $this->getDummyContentFor( $field, $value );

        // insert following data
        if ( strtolower( $fieldElement->getAttribute( 'type' ) ) === 'file' )
        {
            Assertion::assertFileExists( $value, "File '$value' not found (for field '$field')" );
            $fieldElement->attachFile( $value );
        }
        else
            $fieldElement->setValue( $value );
    }

    /**
     * @Given /^(?:|I )am (?:at|on) (?:|the )"([^"]*)" page$/
     * @When  /^(?:|I )go to (?:|the )"([^"]*)"(?:| page)$/
     * @When  /^(?:|I )go to (?:|the )([A-Za-z_-\d\s]*)(?:| page)$/
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
     * @Then /^(?:|I )see "([^"]*)" button with (?:|attributes)(?:|\:)$/
     */
    public function iSeeButtonWithAttributes( $button, TableNode $table )
    {
        $el = $this->getSession()->getPage()->findButton( $button );

        // assert that button was found
        Assertion::assertNotNull( $el, "Could not find '$button' button." );

        Assertion::assertEquals(
            $button,
            $el->getText(),
            "Failed asserting that '$button' is equal to '{$el->getText()}"
        );

        $rows = $table->getRows();
        array_shift( $rows );   // this is needed to take the first row ( readability only )
        foreach ( $rows as $row )
        {
            list( $attribute, $value ) = $row;
            // assert that attribute was found
            Assertion::assertNotNull(
                $el->getAttribute( $attribute ),
                "Couldn't find '$attribute' attribute."
            );

            Assertion::assertEquals(
                $value,
                $el->getAttribute( $attribute ),
                "Value '$value' of '$attribute' attribute doesn't match '{$el->getAttribute( $attribute )}'"
            );
        }
    }

    /**
     * @Then /^(?:|I )see ["'](.+)["'] (?:warning|error)(?:| message)$/
     */
    public function iSeeError( $error )
    {
        $escapedText = $this->getSession()->getSelectorsHandler()->xpathLiteral( $error );

        $page = $this->getSession()->getPage();
        $tmp = array_merge(
            $page->findAll( 'css', 'div.warning' ), // warnings
            $page->findAll( 'css', 'div.error' )    // errors
        );
        foreach ( $tmp as $el )
        {
            $aux = $el->find( 'named', array( 'content', $escapedText ) );
            if ( !empty( $aux ) )
            {
                Assertion::assertContains(
                    $error,
                    $aux->getText(),
                    "Couldn't find '$error' error message in '{$aux->getText()}'"
                );
                return;
            }
        }

        // if not found throw an failed assertion
        Assertion::fail( "Couldn't find '$error' error message" );
    }

    /**
     * @Then /^(?:|I )see "([^"]*)" filled with "([^"]*)"$/
     */
    public function iSeeFilledWith( $field, $value )
    {
        $el = $this->getSession()->getPage()->find( 'xpath', "//input[contains(@id,'$field')]" );

        // assert that field was found
        Assertion::assertNotEmpty(
            $el,
            "Could not find '$field' field."
        );

        $testValue = $el->getValue();
        // check if it is dummy password sent by server, if it is skip this assertion
        if ( $testValue == "_ezpassword" )
            return;

        Assertion::assertEquals(
            gettype( $value ),
            gettype( $testValue ),
            "Expected '$value' value has different type than '$testValue'"
        );

        // assert the value is equal to expected
        Assertion::assertContains(
            $value,
            $testValue,
            "Value '$value' of '$field' field doesn't match '{$el->getValue()}'"
        );
    }

    /**
     * @Then /^(?:|I )see form filled with(?:|\:)$/
     */
    public function iSeeFormFilledWith( TableNode $table )
    {
        $data = $this->convertTableToArrayOfData( $table );

        $newSteps = array();
        array_shift( $data );   // this is needed to take the first row ( readability only )
        foreach ( $data as $field => $value )
            $newSteps[] = new Step\Then( 'I see "' . $field .'" filled with "' . $value . '"' );

        return $newSteps;
    }

    /**
     * @Then /^(?:|I )see form filled with data "([^"]*)"$/
     */
    public function iSeeFormFilledWithData( $identifier )
    {
        Assertion::assertTrue(
            isset( $this->contentHolder[$identifier] ),
            "Content of '$identifier' identifier not found."
        );

        $data = $this->contentHolder[$identifier];

        $newSteps = array();
        foreach ( $data as $field => $value )
            $newSteps[] = new Step\Then( 'I see "' . $field .'" filled with "' . $value . '"' );

        return $newSteps;
    }

    /**
     * @Then /^(?:|I )see form filled with data "([^"]*)" and(?:|\:)$/
     */
    public function iSeeFormFilledWithDataAnd( $identifier, TableNode $table )
    {
        Assertion::assertTrue(
            isset( $this->contentHolder[$identifier] ),
            "Content of '$identifier' identifier not found."
        );

        $data = $this->convertTableToArrayOfData(
            $table,
            $this->contentHolder[$identifier]
        );

        $newSteps = array();
        array_shift( $data );   // this is needed to take the first row ( readability only )
        foreach ( $data as $field => $value )
            $newSteps[] = new Step\Then( 'I see "' . $field .'" filled with "' . $value . '"' );

        return $newSteps;
    }

    /**
     * @Then /^(?:|I )see image "([^"]*)"$/
     */
    public function iSeeImage( $image )
    {
        $expected = $this->getFileByIdentifier( $image );
        Assertion::assertFileExists( $expected, "Parameter '$expected' to be searched isn't a file" );

        // iterate through all images checking
        foreach ( $this->getSession()->getPage()->findAll( 'xpath', '//img[@src]' ) as $img )
        {
            $path = $this->locatePath( $img->getAttribute( "src" ) );
            if ( md5_file( $expected ) == md5_file( $path ) )
            {
                Assertion::assertEquals( 1, 1 );
                return;
            }
        }

        // if it wasn't found throw an failed assertion
        Assertion::fail( "Couln't find '$image' image" );
    }

    /**
     * @Then /^(?:|I )see links for Content objects(?:|\:)$/
     *
     * $table = array(
     *      array(
     *          [link|object],  // mandatory
     *          parentLocation, // optional
     *      ),
     *      ...
     *  );
     *
     * @todo verify if the links are for objects
     * @todo check if it has a different url alias
     * @todo check "parent" node
     */
    public function iSeeLinksForContentObjects( TableNode $table )
    {
        $session = $this->getSession();
        $rows = $table->getRows();
        array_shift( $rows );   // this is needed to take the first row ( readability only )
        $base = $this->makeXpathForBlock( 'main' );
        foreach ( $rows as $row )
        {
            if( count( $row ) >= 2 )
                list( $link, $parent ) = $row;
            else
                $link = $row[0];

            Assertion::assertNotNull( $link, "Missing link for searching on table" );

            $url = $this->literal( str_replace( ' ', '-', $link ) );

            $el = $session->getPage()->find( "xpath", "$base//a[contains(@href, $url)]" );

            Assertion::assertNotNull( $el, "Couldn't find a link for object '$link' with url containing '$url'" );
        }
    }

    /**
     * @Then /^(?:|I )see links for Content objects in following order(?:|\:)$/
     *
     *  @todo check "parent" node
     */
    public function iSeeLinksForContentObjectsInFollowingOrder( TableNode $table )
    {
        $page = $this->getSession()->getPage();
        $base = $this->makeXpathForBlock( 'main' );
        // get all links
        $links = $page->findAll( "xpath", "$base//a[@href]" );

        $i = $passed = 0;
        $last = '';
        $rows = $table->getRows();
        array_shift( $rows );   // this is needed to take the first row ( readability only )

        foreach ( $rows as $row )
        {
            // get values ( if there is no $parent defined on gherkin there is
            // no problem since it will only be tested if it is not empty
            if( count( $row ) >= 2 )
                list( $name, $parent ) = $row;
            else
                $name = $row[0];

            $url = str_replace( ' ', '-', $name );

            // find the object
            while(
                !empty( $links[$i] )
                && strpos( $links[$i]->getAttribute( "href" ), $url ) === false
                && strpos( $links[$i]->getText(), $name ) === false
            )
                $i++;

            $test = !null;
            if( empty( $links[$i] ) )
                $test = null;

            // check if the link was found or the $i >= $count
            Assertion::assertNotNull( $test, "Couldn't find '$name' after '$last'" );

            $passed++;
            $last = $name;
        }

        Assertion::assertEquals(
            count( $rows ),
            $passed,
            "Expected to evaluate '{count( $rows )}' links evaluated '{$passed}'"
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
     * @Then /^(?:|I )see ["'](.+)["'] message$/
     *
     * @todo Find if this messages go into a specific tag.class
     */
    public function iSeeMessage( $message )
    {
        $el = $this->getSession()->getPage()->find(
            "named",
            array(
                "content",
                $this->getSession()->getSelectorsHandler()->xpathLiteral( $message )
            )
        );

        // assert that message was found
        Assertion::assertNotNull(
            $el,
            "Could not find '$message' message."
        );

        Assertion::assertContains(
            $message,
            $el->getText(),
            "Couldn't find '$message' message in '{$el->getText()}'"
        );
    }

    /**
     * @Given /^(?:|I )see search (\d+) result$/
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
