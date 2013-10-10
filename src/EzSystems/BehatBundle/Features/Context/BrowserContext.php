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

use PHPUnit_Framework_Assert as Assertion;
use Behat\Behat\Context\Step;
use Behat\Gherkin\Node\TableNode;
use Behat\Behat\Exception\PendingException;
use eZ\Publish\Core\Base\Exceptions\NotFoundException;
use eZ\Publish\Core\Base\Exceptions\InvalidArgumentException;
use EzSystems\BehatBundle\Features\Context\FeatureContext as BaseFeatureContext;

/**
 * Browser interface helper context.
 */
class BrowserContext extends BaseFeatureContext
{
    /**
     * Find the attribute value related to the needle
     * @param  string       $haystack The complete html
     * @param  string       $needle   The string with the part of the attribute value
     * @param  string       $tag      The tag of where the attribute should belong
     * @param  string       $attr     The attribute to ifnd
     * @return false|string Complete attribute value
     *
     * @todo Find a way to specify the form in which the attribute must be
     *      searched (in case of fields)
     */
    public function findCompleteAttribute( $haystack, $needle, $tag = null, $attr = 'id' )
    {
        $pos = 0;
        $unexpectedValues = array( '<', '>', '=', " " );
        $lenght = strlen( $haystack );
        // find string position
        while( ($pos = strpos( $haystack, $needle, $pos ) ) !== false )
        {
            // get the attribute value
            $i = $j = $pos;
            $start = $end = false;
            while( !$start || !$end )
            {
                // verify if the any non expected character show up
                if(
                    in_array( $haystack[$i], $unexpectedValues )
                    || in_array( $haystack[$j], $unexpectedValues )
                    || $i < 0
                    || $j > $lenght
                )
                    break;

                // get the start position of the attribute value
                if ( $start === false )
                {
                    if( $haystack[$i] == "'" || $haystack[$i] == '"' )
                        $start = $i + 1;
                    else
                        $i--;
                }

                // get the end position of the attribute value
                if ( $end === false )
                {
                    if( $haystack[$j] == "'" || $haystack[$j] == '"' )
                        $end = $j - 1;
                    else
                        $j++;
                }
            }

            // if it was an attribute
            if( $start && $end )
            {
                // verify if it is the right attribute
                if( substr( $haystack, $start - strlen( $attr ) - 2, strlen( $attr ) ) == $attr )
                {
                    // verify if it is the right tag
                    if( !empty( $tag ) )
                    {
                        $tagStart = $start;
                        while( $haystack[$tagStart--] != "<" && $tagStart > 0 );
                        // add 2 for '<' and the extra --
                        $tagStart+= 2;
                    }

                    if(
                        !empty( $tag ) && substr( $haystack, $tagStart, strlen( $tag ) ) == $tag
//                        && strpos( substr( $haystack, $start, $end - $start + 1 ) , "login" ) === false
                        || empty( $tag )
                    )
                        // if all went as expected return the attribute
                        return substr( $haystack, $start, $end - $start + 1 );
                }
            }

            // if the search didn't went well, change the pos position to
            // after the string that was found, or else in the while will find
            // the same position
            $pos = $pos + strlen( $needle );
        }

        return false;
    }

/******************************************************************************
 * **************************       HELPERS         ************************* *
 ******************************************************************************/

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
        if( empty( $formData ) )
            throw new NotFoundException( "form data" );

        $newSteps = array();
        // create the new steps When for adding the data to the form
        foreach( $formData as $field => $value )
        {
            // verify if this field is to be filled
            if(
                empty( $onlyListed )                // all fields
                || $field === $onlyListed           // only specified field
                || in_array( $field, $onlyListed)   // fields in list
            )
            {
                // verify it is single value
                if ( !is_array( $field ) )
                    $newSteps[] = new Step\When( 'I fill "' . $field . '" with "' . $value . '"' );
                // else create Gherkin Table
                else {
                    $fields = "|";
                    foreach( $value as $item )
                    {
                        // verify if its a single row table
                        if( !is_array( $item ) )
                            $fields.= " $item |";
                        // if its a multirow table create rows
                        else {
                            // fill row
                            foreach( $item as $last )
                                $fields.= " $last |";

                            $fields.= "\n|";
                        }
                    }
                }
            }
        }

        return $newSteps;
    }


/******************************************************************************
 * **************************        GIVEN         ************************** *
 ******************************************************************************/

    /**
     * @Given /^I am logged in as "([^"]*)" with password "([^"]*)"$/
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
     * @Given /^I am (?:on|at) "([^"]*)" page$/
     */
    public function iAmAtPage( $page )
    {
        $this->visit(
            $this->locatePath(
                $this->getPathByPageIdentifier( $page )
            )
        );
    }

    /**
     * @Given /^I click at "([^"]*)" link$/
     */
    public function iClickAtLink( $link )
    {
        // get link
        $aux = $this->getSession()->getPage()->findLink( $link );

        Assertion::assertNotNull( $aux, "Couldn't find '$link' link" );

        // if it was found click on it!
        $aux->click();
    }

/******************************************************************************
 * **************************        WHEN          ************************** *
 ******************************************************************************/

    /**
     * @When /^I search for "([^"]*)"$/
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
     * @When /^I go to the "([^"]*)"$/
     */
    public function iGoToThe( $pageIdentifier )
    {
        return array(
            new Step\When( 'I am on "' . $this->getPathByPageIdentifier( $pageIdentifier ) . '"' ),
        );
    }

    /**
     * @When /^I fill "([^"]*)" with "([^"]*)"$/
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

        $fieldElement = $page->find('xpath', "//input[contains(@id,'$field')]");

        // assert that the field was found
        Assertion::assertNotNull(
            $fieldElement,
            "Could not find '$field' field."
        );

        // check if data is in fact an identifier (alias)
        if( $this->isSingleCharacterIdentifier( $value ) )
            $value = $this->getDummmyContentFor( $field, $value );

        // insert following data
        if( strtolower( $fieldElement->getAttribute( 'type') ) === 'file' ) {
            Assertion::assertFileExists( $value, "File '$value' not found (for field '$field')" );
            $fieldElement->attachFile( $value );
        }
        else
            $fieldElement->setValue( $value );
    }

    /**
     * @When /^I fill a valid "([^"]*)" form$/
     */
    public function iFillAValidForm( $form )
    {
        return $this->fillForm( $this->forms[$form] );
    }

    /**
     * @When /^I fill "([^"]*)" form with$/
     */
    public function iFillFormWith( $form, TableNode $table )
    {
        $formData = $this->forms[$form];
        if( empty( $formData ) )
            throw new NotFoundException( 'form', $form );

        $data = $this->convertTableToArrayOfData( $table, $formData );

        // fill the form
        return $this->fillForm( $data );
    }

    /**
     * @When /^I fill form with only$/
     */
    public function iFillFormWithOnly( TableNode $table )
    {
        $data = $this->convertTableToArrayOfData( $table );

        // fill the form
        return $this->fillForm( $data );
    }

    /**
     * @When /^I click at "([^"]*)" button$/
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
     * @When /^I click at "([^"]*)" image$/
     */
    public function iClickAtImage( $image )
    {
        $literal = $this->getSession()->getSelectorsHandler()->xpathLiteral( $image );

        $xpath = "";
        $possibleAttributes = array( 'href', 'id', 'class' );
        foreach( $possibleAttributes as $attribute )
            if( !empty( $xpath ) )
                $xpath.= "|//a[contains(@$attribute,$literal)]/img/..";
            else
                $xpath.= "//a[contains(@$attribute,$literal)]/img/..";

        $el = $this->getSession()->getPage()->find( 'xpath', $xpath );

        // assert that button was found
        Assertion::assertNotNull(
            $el,
            "Could not find '$image' image."
        );

        if( is_array( $el ) )
            $el = $el[0];

        $el->click();
    }

    /**
     * @When /^I attach a file "([^"]*)" to "([^"]*)"$/
     */
    public function iAttachAFileTo( $identifier, $field )
    {
        // check/get file for testing
        if( !is_file( $identifier ) )
            $file =
            $this->contentHolder[$identifier] =
                $this->getDummyContentFor( 'file' );

        return new Step\When( 'I fill "' . $field . '" with "' . $file . '"' );
    }

    /**
     * @When /^I attach a file$/
     */
    public function iAttachAFile()
    {
        // get image for testing
        $file = $this->getDummyContentFor( 'file' );

        return new Step\When( 'I fill "file" with "' . $file . '"' );
    }

    /**
     * @When /^I attach an image "([^"]*)" to "([^"]*)"$/
     */
    public function iAttachAnImageTo( $identifier, $field )
    {
        // check/get image for testing
        if( !is_file( $identifier ) )
            $image =
            $this->contentHolder[$identifier] =
                $this->getDummyContentFor( 'image' );

        return new Step\When( 'I fill "' . $field . '" with "' . $image . '"' );
    }

    /**
     * @When /^I attach an image$/
     */
    public function iAttachAnImage()
    {
        // get image for testing
        $image = $this->getDummyContentFor( 'image' );

        return new Step\When( 'I fill "image" with "' . $image . '"' );
    }

/******************************************************************************
 * **************************        THEN          ************************** *
 ******************************************************************************/

    /**
     * @Then /^I am (?:on|at) the "([^"]*)"$/
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
     * @Then /^(?:|I )want dump of (?:|the )page$/
     */
    public function iWantDumpOfThePage()
    {
        echo $this->getSession()->getPage()->getContent();
    }

    /**
     * @Then /^I see search (\d+) result$/
     */
    public function iSeeSearchResults( $total )
    {
        $resultCountElement = $this->getSession()->getPage()->find( 'css', 'div.feedback' );

        Assertion::assertNotNull(
            $resultCountElement,
            'Could not find result count text element.'
        );

        Assertion::assertEquals(
            "Search for \"{$this->priorSearchPhrase}\" returned {$total} matches",
            $resultCountElement->getText()
        );
    }

    /**
     * @Then /^I should be redirected to "([^"]*)"$/
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
     * @Then /^I see (\d+) "([^"]*)" elements listed$/
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
     * @Then /^I see "([^"]*)" page$/
     *
     * @todo Make the page comparison better
     */
    public function iSeePage( $page )
    {
        $currentUrl = $this->getUrlWithoutQueryString( $this->getSession()->getCurrentUrl() );

        $expectedUrl = $this->locatePath( $this->getPathByPageIdentifier( $page ) );

        Assertion::assertEquals(
            $expectedUrl,
            $currentUrl,
            "Unexpected URL of the current site. Expected: '$expectedUrl'. Actual: '$currentUrl'."
        );
    }

    /**
     * @Then /^I see ["'](.+)["'] (?:warning|error)$/
     *
     * @todo Find if error messages should be separeted from warnings or they should be together
     */
    public function iSeeError( $error )
    {
        $escapedText = $this->getSession()->getSelectorsHandler()->xpathLiteral( $error );

        $page = $this->getSession()->getPage();
        foreach(
            array_merge(
                $page->findAll( 'css', 'div.warning' ),   // warnings
                $page->findAll( 'css', 'div.error' )      // errors
            )
            as $el
        ) {
            $aux = $el->find( 'named', array( 'content', $escapedText ) );
            if( !empty( $aux ) ) {
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
     * @Then /^I see ["'](.+)["'] message$/
     *
     * @todo Find if this messages go into a specific tag.class
     */
    public function iSeeMessage( $message )
    {
        $el = $this->getSession()->getPage()->find( "named", array(
                "content",
                $this->getSession()->getSelectorsHandler()->xpathLiteral( $message )
        ));

        // assert that button was found
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
     * @Given /^I don\'t see ["'](.+)["'] message$/
     */
    public function iDonTSeeMessage( $message )
    {
        $el = $this->getSession()->getPage()->find( "named", array(
                "content",
                $this->getSession()->getSelectorsHandler()->xpathLiteral( $message )
        ));

        // assert that button was found
        Assertion::assertNull(
            $el,
            "Unexpected '$message' message found."
        );
    }


    /**
     * @Then /^I don\'t see "([^"]*)" button$/
     */
    public function iDonTSeeButton( $button )
    {
        $el = $this->getSession()->getPage()->findButton( $button );

        if( !empty( $el ) )
            Assertion::assertNotEquals(
                $button,
                $el->getText(),
                "Unexpected '$button' button is present"
            );
        else
            Assertion::assertNull( $el );
    }

    /**
     * @Then /^I see "([^"]*)" button$/
     */
    public function iSeeButton( $button )
    {
        $el = $this->getSession()->getPage()->findButton( $button );

        // assert that button was found
        Assertion::assertNotNull(
            $el,
            "Could not find '$button' button."
        );

        Assertion::assertEquals(
            $button,
            $el->getText(),
            "'$button' button is different than '{$el->getText()}'"
        );
    }

    /**
     * @Then /^I see "([^"]*)" button with attributes$/
     */
    public function iSeeButtonWithAttributes( $button, TableNode $table )
    {
        $el = $this->getSession()->getPage()->findButton( $button );

        // assert that button was found
        Assertion::assertNotNull(
            $el,
            "Could not find '$button' button."
        );

        Assertion::assertEquals(
            $button,
            $el->getText(),
            "Failed asserting that '$button' is equal to '{$el->getText()}"
        );

        foreach( $table->getRows() as $attribute => $value )
        {
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
     * @Then /^I see image "([^"]*)"$/
     */
    public function iSeeImage( $image )
    {
        throw new PendingException( "Image finder" );

        $expected = $this->getFileByIdentifier( $image );
        Assertion::assertFileExists( $expected, "Parameter '$expected' to be searched isn't a file" );

        // iterate through all images checking
        foreach( $this->getSession()->getPage()->findAll( 'xpath', '//img' ) as $img )
        {
            $path = $this->locatePath( $img->getAttribute( "src" ) );
            if( md5_file( $expected ) == md5_file( $path ) ) {
                Assertion::assertFileEquals( $expected , $path );
                return;
            }
        }

        // if it wasn't found throw an failed assertion
        Assertion::fail( "Couln't find '$image' image" );
    }

    /**
     * @Then /^I don\'t see image "([^"]*)"$/
     */
    public function iDonTSeeImage( $image )
    {
        throw new PendingException( "Image finder" );

        $expected = $this->getFileByIdentifier( $image );
        Assertion::assertFileExists( $expected, "Parameter '$expected' to be searched isn't a file" );

        // iterate through all images checking
        foreach( $this->getSession()->getPage()->findAll( 'xpath', '//img' ) as $img )
        {
            $path = $this->locatePath( $img->getAttribute( "src" ) );
            Assertion::assertFileNotEquals(
                $expected,
                $path,
                "Unexpected '$image' image found"
            );
        }
    }

    /**
     * @Then /^I see form filled with data "([^"]*)"$/
     */
    public function iSeeFormFilledWithData( $identifier )
    {
        Assertion::assertTrue(
            isset( $this->contentHolder[$identifier] ),
            "Content of '$identifier' identifier not found."
        );

        $data = $this->contentHolder[$identifier];

        $newSteps = array();
        foreach( $data as $field => $value ) {
            $newSteps[]= new Step\Then( 'I see "' . $field .'" filled with "' . $value . '"' );
        }

        return $newSteps;
    }

    /**
     * @Then /^I see form filled with data "([^"]*)" and$/
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
        foreach( $data as $field => $value ) {
            $newSteps[]= new Step\Then( 'I see "' . $field .'" filled with "' . $value . '"' );
        }

        return $newSteps;
    }

    /**
     * @Then /^I see form filled with$/
     */
    public function iSeeFormFilledWith( TableNode $table )
    {
        $data = $this->convertTableToArrayOfData( $table );

        $newSteps = array();
        foreach( $data as $field => $value ) {
            $newSteps[]= new Step\Then( 'I see "' . $field .'" filled with "' . $value . '"' );
        }

        return $newSteps;
    }


    /**
     * @Then /^I see "([^"]*)" filled with "([^"]*)"$/
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
        if( $testValue == "_ezpassword" )
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
     * @Then /^I see "([^"]*)" input$/
     */
    public function iSeeInput( $input )
    {
        Assertion::assertNotNull(
            $this->getSession()->getPage()->find( 'xpath',
                "//input[contains(@id,'$input')]|//input[contains(@name,'$input')]"
            ),
            "Input '$input' wasn't found"
        );
    }

    /**
     * @Then /^I see "([^"]*)" input "([^"]*)"$/
     */
    public function iSeeInput2( $input, $attribute )
    {
        Assertion::assertNotNull(
            $this->getSession()->getPage()->find( 'xpath',
                "//input[@$attribute and contains(@id,'$input')]|//input[@$attribute and contains(@name,'$input')]"
            ),
            "Field '$input' with attribute '$attribute' not found"
        );
    }

    /**
     * @Then /^I see links in$/
     */
    public function iSeeLinksIn( TableNode $table )
    {
        $session = $this->getSession();
        foreach( $table->getRows as $row )
        {
            // prepare data
            if( count( $row ) != 2 )
                throw new InvalidArgumentException( $row, "should be an array with link and tag" );
            list( $link, $type ) = $row;

            $tag = $this->getXpathTagsFor( $type );

            $literal = $session->getSelectorsHandler()->xpathLiteral( $link );
            $el = $session->getPage()->find( "xpath", "$tag//a[@href and text() = $literal]" );

            Assertion::assertNotNull( $el, "Couldn't find a link with '$link' text and inside '$tag' tag" );
        }
    }

    /**
     * @Then /^I see links for Content objects$/
     *
     * $table = array(
     *      array(
     *          [link|object],  // mandatory
     *          parentLocation, // optional
     *      ),
     *      ...
     *  );
     *
     * @todo check if it has a different url alias
     * @todo check "parent" node
     */
    public function iSeeLinksForContentObjects( TableNode $table )
    {
        throw new PendingException( "Check links for objects" );

        $session = $this->getSession();
        foreach( $table->getRows as $row )
        {
            list( $link, $parent ) = $row;

            Assertion::assertNotNull( $link, "Missing link for searching on table" );

            $url = str_replace( ' ', '-', $link );
            $el = $session->getPage()->find( "xpath", "//a[contains(@href, $url)]" );

            Assertion::assertNotNull( $el, "Couldn't find a link for object '$link' with url containing '$url'" );
        }
    }

    /**
     * @Then /^I don\'t see links$/
     */
    public function iDonTSeeLinks( TableNode $table )
    {
        $session = $this->getSession();
        foreach( $table->getRows as $row )
        {
            $link = $row[0];
            $url = str_replace( ' ', '-', $link );
            $literal = $session->getSelectorsHandler()->xpathLiteral( $link );
            $el = $session->getPage()->find( "xpath", "//a[contains(@href, $url) or (text() = $literal and @href)]" );

            Assertion::assertNull( $el, "Unexpected link found '{$el->getText()}'" );
        }
    }

    /**
     * @Then /^I don\'t see any Content object link$/
     */
    public function iDonTSeeAnyContentObjectLink()
    {
        // get all links
        $links = $this->getSession()->getPage()->findAll( "xpath", "//a[starts-with(@href, "/")]" );

        foreach( $links as $link )
        {
            Assertion::assertNull(
                $this->loadContentObjectByUrl( $link->getAttribute( "href" ) ),
                "Unexpected object found with url alias '{$link->getAttribute( "href" )}'"
            );
        }
    }

    /**
     * @Then /^I see (\d+) Content object links$/
     */
    public function iSeeContentObjectLinks( $total )
    {
        // get all links
        $links = $this->getSession()->getPage()->findAll( "xpath", "//a[starts-with(@href, "/")]" );

        $count = 0;
        foreach( $links as $link )
        {
            // count only valid links
            $el = $this->loadContentObjectByUrl( $link->getAttribute( "href" ) );
            if( !empty( $el ) )
                $count++;
        }

        Assertion::assertEquals(
            $total, $count,
            "Expecting '$total' links found '$count'"
        );
    }

    /**
     * @Then /^I see (\d+) ([^\s]*)(?:|s) link(?:|s)$/
     */
    public function iSeeLink( $total, $type ) {
        $count = count(
            $this->getSession()->getPage()->findAll(
                "xpath",
                $this->getXpathTagsFor( $type ) . "//a[@href]"
            )
        );
        Assertion::assertEquals(
            $count, $total,
            "Expecting '$total' links found '$count'"
        );
    }

    /**
     * @Then /^I see links for Content objects in following order$/
     */
    public function iSeeLinksForContentObjectsInFollowingOrder( TableNode $table )
    {
        $page = $this->getSession()->getPage();
        // get all links
        $links = $page->findAll( "xpath", "//a[@href]" );

        $i = 0;
        $last = 'inicial';
        foreach( $table->getRows() as $row )
        {
            // get values ( if there is no $parent defined on gherkin there is
            // no problem since it will only be tested if it is not empty
            list( $name, $parent ) = $row;

            $literal = $this->getSession()->getSelectorsHandler()->xpathLiteral( $name );
            $url = str_replace( ' ', '-', $literal );

            // find the object
            while(
                !empty( $links[$i] )
                // this will check if inside the link it finds the url inside href or the text has the name
                && $links[$i]->find( "xpath", "//*[contains(@href,$url) or contains(text(),$literal)][@href]") != null
            )
                $i++;

            // check if the link was found or the $i >= $count
            Assertion::assertNotNull( $links[$i], "Couldn't find '$name' after '$last'" );

            // check if there is a need to confirm parent
            if( !empty( $parent ) ){
                $parentLiteral = $this->getSession()->getSelectorsHandler()->xpathLiteral( $parent );
                $parentUrl = str_replace( ' ', '-', $parentLiteral );
                $xpath =
                    "../../../"
                    . $this->getXpathTagsFor( 'topic' )
                    . "/*[contains(@href,$parentUrl) or contains(text(),$parentLiteral)]";
                Assertion::assertNotNull(
                    $links[$i]->find( "xpath", $xpath ),
                    "Couldn't find '$parent' parent of '$name' link"
                );
            }

            $last = $name;
        }
    }
}