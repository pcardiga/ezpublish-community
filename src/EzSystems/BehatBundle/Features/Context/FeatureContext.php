<?php
/**
 * File containing the FeatureContext class.
 *
 * This class contains general feature context for Behat.
 *
 * @copyright Copyright (C) 1999-2013 eZ Systems AS. All rights reserved.
 * @license http://www.gnu.org/licenses/gpl-2.0.txt GNU General Public License v2
 * @version //autogentag//
 */

namespace EzSystems\BehatBundle\Features\Context;

use Behat\Behat\Context\Step;
use Behat\Gherkin\Node\TableNode;
use Behat\MinkExtension\Context\MinkContext;
use Behat\Behat\Event\ScenarioEvent;
use Behat\Mink\Exception\UnsupportedDriverActionException as MinkUnsupportedDriverActionException;
use Behat\Symfony2Extension\Context\KernelAwareInterface;
use PHPUnit_Framework_Assert as Assertion;
use Symfony\Component\HttpKernel\KernelInterface;
use eZ\Publish\Core\Base\Exceptions\NotFoundException;
use eZ\Publish\Core\Base\Exceptions\InvalidArgumentType;
use eZ\Publish\Core\Base\Exceptions\InvalidArgumentException;
use Behat\Behat\Exception\PendingException;
use EzSystems\BehatBundle\Features\Context\ContentManager;

/**
 * Feature context.
 */
class FeatureContext extends MinkContext implements KernelAwareInterface
{
    /**
     * @var \Symfony\Component\HttpKernel\KernelInterface
     */
    private $kernel;

    /**
     * @var array
     */
    private $parameters;

    /**
     * @var \EzSystems\BehatBundle\Features\Context\ContentManager
     *      This will help in the managing of content for the tests that need
     *      to CRUD content
     */
    private $contentManager;

    /**
     * @var array Array to map identifier to urls, should be set by child classes.
     */
    protected $pageIdentifierMap = array();

    /**
     * Valid Forms, should be set by child classes.
     * Should be defined by key => array( $data )
     * $data = array(
     *      "field1" => "value1",
     *      "field2" => array( "if, "it", "has", "multiple", "values" ),
     *      ...
     *      "fieldN" => "valueN",
     * );
     * @var array
     */
    protected $forms = array();

    /**
     * This will tell us which containers to search, should be set by child classes.
     * ex:
     * $mainAttributes = array(
     *      "content"   => "thisIsTheIdOftheMainContentDiv",
     *      "column"    => array( "class" => "thisIstheClassOfTheColumns" ),
     *      ...
     * );
     * @var array This will have a ( identifier => array )
     */
    protected $mainAttributes = array();

    /**
     * Content holder will have the data for the reuse data
     * This is an array of (identifier => data), that have the data that will be used
     * or tested in later sentences.
     * @var array This will have a ( identifier => array ) for the data to be retested
     */
    protected $contentHolder = array();

    /**
     * @var string
     */
    protected $priorSearchPhrase = '';

    /**
     * Initializes context with parameters from behat.yml.
     *
     * @param array $parameters
     */
    public function __construct( array $parameters )
    {
        $this->parameters = $parameters;
        $this->useContext( "interface", new InterfaceHelperContext( $parameters ) );
        $this->contentManager = new ContentManager();
    }

    /**
     * Sets HttpKernel instance.
     * This method will be automatically called by Symfony2Extenassertsion ContextInitializer.
     *
     * @param KernelInterface $kernel
     */
    public function setKernel( KernelInterface $kernel )
    {
        $this->kernel = $kernel;
    }


     /**
      * @BeforeScenario
      *
      * Unset contentHolder
      * There are some scenarios that use loads of memory, so it might prejudice
      * the following scenarios, throwing false positives errors
      */
     public function cleanContentHolder()
     {
         unset( $this->contentHolder );
     }

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
     * @When /^I go to the "([^"]*)"$/
     */
    public function iGoToThe( $pageIdentifier )
    {
        return array(
            new Step\When( 'I am on "' . $this->getPathByPageIdentifier( $pageIdentifier ) . '"' ),
        );
    }

    /**
     * Returns the path associated with $pageIdentifier
     *
     * @param string $pageIdentifier
     *
     * @return string
     */
    protected function getPathByPageIdentifier( $pageIdentifier )
    {
        if ( !isset( $this->pageIdentifierMap[$pageIdentifier] ) )
        {
            throw new \RuntimeException( "Unknown page identifier '{$pageIdentifier}'." );
        }

        return $this->pageIdentifierMap[$pageIdentifier];
    }

    /**
     * Returns $url without its query string
     *
     * @param string $url
     *
     * @return string
     */
    protected function getUrlWithoutQueryString( $url )
    {
        if ( strpos( $url, '?' ) !== false )
        {
            $url = substr( $url, 0, strpos( $url, '?' ) );
        }

        return $url;
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

/******************************************************************************
 * **************************       HELPERS         ************************* *
 ******************************************************************************/

    /**
     * This function will convert Gherkin tables into structure array of data
     *
     * @param \Behat\Gherkin\Node\TableNode $table The Gherkin table to extract the values
     * @param null|array                    $data  If there are values to be concatenated/updated
     *
     * @return false|array
     *
     * the returned array should look like:
     *      $data = array(
     *          "field1" => "single value 1",
     *          "field2" => "single value 2",
     *          "field3" => array( "multiple", "value", "here"),
     *          ...
     *      );
     */
    protected function convertTableToArrayOfData( TableNode $table, $data = null )
    {
        if( empty( $data ) )
            $data = array();

        // prepare given data
        $i = 0;
        foreach( $table->getRows() as $row )
        {
            $count = count( array_filter( $row ) );
            // check if the field is supposed to be empty
            // or it simply has only 1 element
            if(
                $count == 1
                && count( $row )
                && !method_exists( $table, "getCleanRows" )
            ) {
                $count = 2;
            }

            $key = $row[0];
            switch( $count ){
            // case 1 is for the cases where there is an Examples table and it
            // gets the values from there, so the field name/id shold be on the
            // examples table (ex: "| <field_name> |")
            case 1:
                $value = $key;
                $aux = $table->getCleanRows();
                $key = str_replace( array( '<' , '>' ), array( '', '' ), $aux[$i][0] );
                break;

            // case 2 is the most simple case where "| field1 | as value 1 |"
            case 2:
                $value = $row[1];
                break;

            // this is for the cases where there are several values for the same
            // field (ex: author) and the gherkin table should look like
            default: $value = array_slice( $row, 1 );
                break;
            }
            $data[$key] = $value;
            $i++;
        }

        // if its empty return false otherwise return the array with data
        return  empty( $data )? false : $data;
    }

    /**
     * Verify if a value is a single character identifier
     *
     * @param  mixed  $value The value to test if is identifier
     *
     * @return boolean
     */
    protected function isSingleCharacterIdentifier( $value )
    {
        return is_string( $value ) && strlen( $value ) == 1 && $value >= 'A' && $value <= 'Z';
    }

    /**
     * This is an helper to create a search for attributes to concatenate
     *
     * @param  string|array $container     This field can have multiple values
     *  ex:
     *  $container = "singleValue";         // this can be ID or class
     *  $container = array(
     *      "attribute1" => "singleValue,   // this will retrive a string "@attribute1 = 'singleValue'"
     *      "attr2" => array(
     *          "value1",                   // for this case it will return a string with
     *          "value2",                   // (@attr2='value1' or @attr2='value2' or ...)
     *          ...
     *      ),
     *  );                                  // finaly all array will concatenate with "and"'s
     *                                      // "@attribute1 = 'singleValue' and (@attr2='value1' or @attr2='value2' or ...)"
     * @param  boolean      $completeXpath This field simple tells if the xpath is to be returned complete or only the attribute search
     *
     * @return string       The search data to be inserted into a XPath
     */
    protected function makeXpathAttributesSearch( $container, $completeXpath = true )
    {
        $handler = $this->getSession()->getSelectorsHandler();
        // check if single value (can be id or class)
        if( !is_array( $container ) ) {
            $literal = $handler->xpathLiteral( $container );
            return "@id = $literal or @class = $literal";
        }

        $result = "";
        foreach( $container as $attribute => $value )
        {
            // check if the attribute have several possible values
            if( !is_array( $value ) ) {
                 $aux = "@$attribute = " . $handler->xpathLiteral( $value );
            }
            // if it has several values for same attribute
            // it needs to make the (@attribute = value1 or @attribute = value2 or ...)
            else {
                $aux = "(";
                for( $i = 0; !empty( $value[$i+1] );$i++ )
                    $aux.= "@$attribute = " . $handler->xpathLiteral( $value[$i] ) . " or ";

                $aux.= "@$attribute = " . $handler->xpathLiteral( $value[$i+1] ) . ")";
            }

            // check if there is another attribute before this
            if( !empty( $result ) )
                $result.= " and ";

            // finaly merge the search text to the final result
            $result.= $aux;
        }
        return ( $completeXpath )? "//*[$result]": $result;
    }

    /**
     * With the help of FeatureContext::makeXpathAttributesSearch() this will
     * return the search xpath part for an specific container (or any other tag ex: <a>)
     *
     * @param  string  $container     This is the key value for the $mainAttributes holder
     * @param  boolean $completeXpath Boolean to retrieve complete xpath or not
     *
     * @return string  @see FeatureContext::makeXpathAttributesSearch()
     *
     * @todo A way to handle tag's and not attributes (since makeXpathAttributes only handle attributes)
     */
    protected function makeMainAttributeXpathSearch( $container, $completeXpath = true )
    {
        Assertion::assertNotNull(
            $this->mainAttributes[ $container ],
            "Couldn't find the attributes for '$container' container"
        );

        return $this->makeXpathAttributesSearch( $container, $completeXpath );
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
    protected function getXpathTagsFor( $type )
    {
        switch ( strtolower( $type ) ){
        case "topic":
        case "header":
        case "title":
            return "(//h1 | //h2 | //h3)";
        case "list":
            return "(//li)";
        }

        throw new PendingException( "Tag's for '$type' type not defined" );
    }

    /**
     * Sometimes is hard to had a simple setting definition to a table without
     * the need to create most complex sentences and/or add more sentences to the
     * scenario making it hard to understand, so this function makes it possible
     * to add a random setting/definition (or other if intended) without the needs
     * to define a specific sentence to take care of it
     *
     * @param  mixed $value The parameter to check inside configurations
     * to have multiple values it need to be separed by ':' :
     * ex:
     *  single value ex:   $value = "key:value";
     *  multiple value ex: $value = "key:value1:value2:...";
     *
     * @return array
     * ex:
     *  return array( "key" => "value" ); // most simple cases
     * however can have multiple values:
     *  return array( "key" => array( "value1", "value2, ... ) );
     */
    protected function getSettingsFromMultipleValue( $value )
    {
        $result = explode( ':', $value );

        // check if value if it is simple value
        if( !is_string( $value ) || $value == $result )
            return $value;

        // otherwise make the key => value(s)
        $key = array_shift( $result );

        // return the value depending if it is a simple value or an array of values
        if( count( $result ) == 1 )
            return array( $key => $result[0] );
        else
            return array( $key => $result );
    }

        /************************************************************
         * ********      HELPERS -- Content Managing       ******** *
         ************************************************************/

    /**
     * Return the data for the specific identifier
     *
     * @param  string $identifier
     *
     * @return mixed
     */
    protected function getContentByIdentifier( $identifier )
    {
        return isset( $this->contentHolder[$identifier] ) ?
            $this->contentHolder[$identifier]:
            $this->contentManager->getDataByIdentifier( $identifier );
    }

    /**
     * Shortcut to verify if it is a file or get it through identifier
     *
     * @param  string $identifier
     *
     * @return mixed
     */
    protected function getFileByIdentifier( $identifier )
    {
        return is_file( $identifier )?
            $identifier:
            $this->getContentByIdentifier( $identifier );
    }

    /**
     * This will cover the dummy data
     *
     * @param  string      $type       This is for specifying what kind of dummy data to return/store
     * @param  null|string $identifier This is the identifier for the content holder
     */
    protected function getDummyContentFor( $type, $identifier = null )
    {
        $data = null;
        // get/make the intended data
        switch( $type ) {
        case 'integer':
        case 'ezinteger':
            $data = rand( 1000, 99999999 );
            break;

        case 'float':
        case 'ezfloat':
            $data = rand( 1000, 99999999 ) / rand( 10, 500 );
            break;

        case 'identifier':
            $data = "id" . rand( 1000, 9999 ) . "RND";
            break;

        case 'text':
        case 'eztext':
        case 'string':
        case 'ezstring':
            $data = "This is a text string with some rand data " . rand( 1000, 9999 );
            break;

        default:
            throw new PendingException( "Define dummy data for '$type' type" );
        }

        // if a identifier was passed is to create/update a key on content holder
        if( $identifier )
            $this->contentHolder[$identifier] = $data;

        return $data;
    }

    /**
     * This function returns null or the object for the url alias
     *
     * @param  string     $path URL alais for an object (ex: "/folder/objectXpto" )
     *
     * @return null|object
     */
    protected function loadContentObjectByUrl( $path )
    {
        throw new PendingException( "Content managing: Load content by url alias" );
    }


    /**
     * This will get/make dummy data to create/update a Content object
     *
     * @param  string $identifier Identifier of the Content Type
     *
     * @return array
     */
    protected function getDummyDataForContentObjectOfContentType( $identifier )
    {
        if( isset( $this->contentHolder[ $identifier ]['fields'] ) )
            $fieldDefinitions = $this->contentHolder[ $identifier ];
        else
            $fieldDefinitions = $this->contentManager->getFieldDefinitionsOfContentType( $identifier );

        Assertion::assertNotNull( $fieldDefinitions, "Couldn't find Field definitions of Content Type '$identifier'" );

        // get the dummy data
        $data = array();
        foreach( $fieldDefinitions as $field => $type )
        {
            // construct the data array
            $data[$field] = $this->getDummyContentFor( $type );
        }

        return $data;
    }

        /************************************************************
         * ********    GIVEN -- Content Managing Helper    ******** *
         ************************************************************/

    /**
     * @Given /^I have an User with$/
     */
    public function iHaveAnUserWith( TableNode $table )
    {
        throw new PendingException( "Content managing: User create" );
    }

    /**
     * @Given /^I have a Content Type "([^"]*)" with$/
     */
    public function iHaveAContentTypeWith( $identifier, TableNode $table )
    {
        throw new PendingException( "Content managing: Content Type create" );
    }

    /**
     * @Given /^I have a Content object "([^"]*)" of Content Type "([^"]*)" with$/
     */
    public function iHaveAContentObjectOfContentTypeWith( $identifier, $contentTypeIdentifier, TableNode $table )
    {
        throw new PendingException( "Content managing: Content create" );
    }

    /**
     * @Given /^I have a Content object Draft "([^"]*)" of Content Type "([^"]*)"$/
     */
    public function iHaveAContentObjectDraftOfContentType( $identifier, $contentTypeIdentifier )
    {
        throw new PendingException( "Content managing: Content Draft create" );
    }

    /**
     * @Given /^I have a Content object "([^"]*)" of Content Type "([^"]*)"$/
     */
    public function iHaveAContentObjectOfContentType( $identifier, $contentTypeIdentifier )
    {
        throw new PendingException( "Content managing: Content create" );
    }

    /**
     * @Given /^I have the following Content objects of Content Type "([^"]*)"$/
     */
    public function iHaveTheFollowingContentObjectsOfContentType( $contentTypeIdentifier, TableNode $table )
    {
        throw new PendingException( "Content managing: Content create" );

        foreach( $table->getRows() as $row )
        {
            // get main options
            list( $identifier, $location ) = $row;

            // check if there are any settings/definitions to take care of
            $aux = array_splice( $row, 2 );
            if( !empty( $aux ) )
                $settings = $this->getSettingsForContentObject( $aux );

            // get dummy data for this content type
            $dummyData = $this->getDummyDataForContentObjectOfContentType( $contentTypeIdentifier, $identifier, $location );

            // and finaly create content
            $this->contentManager->createContentObject(
                $contentTypeIdentifier,
                array_merge( $dummyData, $settings )
            );

            // add feilds to contentHolder
        }
    }

    /**
     * @Given /^I have (\d+) Content objects of Content Type "([^"]*)" containing (\d+) Content objects of Content Type "([^"]*)"$/
     */
    public function iHaveContentObjectsOfContentTypeContainingContentObjectsOfContentType( $totalOfContainer, $containerContentTypeIdentifier, $totalOfLeafs, $leafContentTypeIdentifier )
    {

    }

    /**
     * @Given /^I have an average "([^"]*)" stars with "([^"]*)" votes on Content object "([^"]*)"$/
     */
    public function iHaveAnAverageStarsWithVotesOnContentObject( $value, $totalVotes, $object )
    {
        throw new PendingException( "Content managing: voting system" );
    }

    /**
     * @Given /^I don\'t have Content object "([^"]*)"$/
     *
     */
    public function iDonTHaveContentObject( $object )
    {
        throw new PendingException( "Content managing: Content delete" );
    }

        /************************************************************
         * ********     WHEN -- Content Managing Helper    ******** *
         ************************************************************/

    /**
     * @When /^I update Content object "([^"]*)" to$/
     */
    public function iUpdateContentObjectTo( $identifier, TableNode $table )
    {
        throw new PendingException( "Content managing: Content update" );
    }

        /************************************************************
         * ********     THEN -- Content Managing Helper    ******** *
         ************************************************************/

    /**
     * @Given /^I see Content object "([^"]*)"$/
     */
    public function iSeeContentObject( $identifier )
    {
        throw new PendingException( "Content managing for Content check/verify." );
    }

        /************************************************************
         * ********    GIVEN -- System Managment Helper    ******** *
         ************************************************************/

    /**
     * @Given /^I have "([^"]*)" active with$/
     */
    public function iHaveActiveWith( $extension, TableNode $table )
    {
        throw new PendingException( "System managing to enable an extension (with definitions)" );
    }

/******************************************************************************
 * **************************        GIVEN         ************************** *
 ******************************************************************************/

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

        // throw exception if the link wans't found
        if( empty( $aux ) )
            throw new NotFoundException( "link", $link );

        // if it was found click on it!
        $aux->click();
    }

    /**
     * @Given /^I got "([^"]*)" disabled$/
     */
    public function iGotDisabled( $parameter )
    {
        switch( $parameter ) {
        default:
            throw new PendingException( "Define disabling '$parameter'" );
        }
    }

/******************************************************************************
 * **************************        WHEN          ************************** *
 ******************************************************************************/

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
        return $this->getSubcontext( "interface" )->fillForm( $this->forms[$form] );
    }

    /**
     * @When /^I fill "([^"]*)" form with$/
     */
    public function iFillFormWith( $form, TableNode $table )
    {
        if( empty( $this->forms[$form] ) )
            throw new NotFoundException( 'form', $form );

        $data = $this->convertTableToArrayOfData( $table, $this->forms[$form] );

        // fill the form
        return $this->getSubcontext( "interface" )->fillForm( $data );
    }

    /**
     * @When /^I fill form with only$/
     */
    public function iFillFormWithOnly( TableNode $table )
    {
        $data = $this->convertTableToArrayOfData( $table );

        // fill the form
        return $this->getSubcontext( "interface" )->fillForm( $data );
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
     * @Then /^I see "([^"]*)" error$/
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
        throw new PendingException();

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
        throw new PendingException();

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

        $data = $this->convertTableToArrayOfData( $table, $this->contentHolder[$identifier] );

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
        $count = count( $this->getSession()->getPage()->findAll( "xpath", $this->getXpathTagsFor( $type ) . "//a[@href]" ) );
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
        $count = count( $links );
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
                $xpath = "../../../" . $this->getXpathTagsFor( 'topic' ) . "/*[contains(@href,$parentUrl) or contains(text(),$parentLiteral)]";
                Assertion::assertNotNull(
                    $links[$i]->find( "xpath", $xpath ),
                    "Couldn't find '$parent' parent of '$name' link"
                );
            }

            $last = $name;
        }
    }
}