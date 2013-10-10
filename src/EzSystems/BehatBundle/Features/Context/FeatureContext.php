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
use Behat\Behat\Exception\PendingException;
use eZ\Publish\Core\Base\Exceptions\NotFoundException;
use eZ\Publish\Core\Base\Exceptions\InvalidArgumentType;
use eZ\Publish\Core\Base\Exceptions\InvalidArgumentException;
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
    public $contentManager;

    /**
     * @var array Array to map identifier to urls, should be set by child classes.
     */
    public $pageIdentifierMap = array();

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
    public $forms = array();

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
    public $mainAttributes = array();

    /**
     * Content holder will have the data for the reuse data
     * This is an array of (identifier => data), that have the data that will be used
     * or tested in later sentences.
     * @var array This will have a ( identifier => array ) for the data to be retested
     */
    public $contentHolder = array();

    /**
     * @var string
     */
    public $priorSearchPhrase = '';

    /**
     * Initializes context with parameters from behat.yml.
     *
     * @param array $parameters
     */
    public function __construct( array $parameters )
    {
        $this->parameters = $parameters;
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
         $this->contentHolder = array();
     }

    /**
     * Returns the path associated with $pageIdentifier
     *
     * @param string $pageIdentifier
     *
     * @return string
     */
    public function getPathByPageIdentifier( $pageIdentifier )
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
    public function getUrlWithoutQueryString( $url )
    {
        if ( strpos( $url, '?' ) !== false )
        {
            $url = substr( $url, 0, strpos( $url, '?' ) );
        }

        return $url;
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
    public function convertTableToArrayOfData( TableNode $table, $data = null )
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
    public function isSingleCharacterIdentifier( $value )
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
    public function makeXpathAttributesSearch( $container, $completeXpath = true )
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
    public function makeMainAttributeXpathSearch( $container, $completeXpath = true )
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
    public function getXpathTagsFor( $type )
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
    public function getSettingsFromMultipleValue( $value )
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
    public function getContentByIdentifier( $identifier )
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
    public function getFileByIdentifier( $identifier )
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
    public function getDummyContentFor( $type, $identifier = null )
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
    public function loadContentObjectByUrl( $path )
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
    public function getDummyDataForContentObjectOfContentType( $identifier )
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

            // add fields to contentHolder
            $this->contentHolder['identifier'] = array( "data" => array_merge( $dummyData, $settings ) );
        }
    }

    /**
     * @Given /^I have (\d+) Content objects of Content Type "([^"]*)" containing (\d+) Content objects of Content Type "([^"]*)"$/
     */
    public function iHaveContentObjectsOfContentTypeContainingContentObjectsOfContentType( $totalOfContainer, $containerContentTypeIdentifier, $totalOfLeafs, $leafContentTypeIdentifier )
    {
        throw new PendingException( "Content managing: Content create" );
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
     * @Given /^I got "([^"]*)" disabled$/
     */
    public function iGotDisabled( $setting )
    {
        switch( $setting ) {
        default:
            throw new PendingException( "Define disable '$setting'" );
        }
    }

    /**
     * @Given /^I got "([^"]*)" enabled$/
     */
    public function iGotEnabled( $setting )
    {
        switch( $setting ) {
        default:
            throw new PendingException( "Define disable '$setting'" );
        }
    }

/******************************************************************************
 * **************************        WHEN          ************************** *
 ******************************************************************************/

/******************************************************************************
 * **************************        THEN          ************************** *
 ******************************************************************************/
}