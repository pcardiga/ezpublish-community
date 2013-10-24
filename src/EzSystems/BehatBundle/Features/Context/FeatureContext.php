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

use Behat\Gherkin\Node\TableNode;
use Behat\MinkExtension\Context\MinkContext;
use Behat\Symfony2Extension\Context\KernelAwareInterface;
use Behat\Behat\Exception\PendingException;
use PHPUnit_Framework_Assert as Assertion;
use Symfony\Component\HttpKernel\KernelInterface;
use EzSystems\BehatBundle\Features\Context\Helpers\ContentManager;

/**
 * Feature context.
 */
class FeatureContext extends MinkContext implements KernelAwareInterface
{
    /**
     * @var \Symfony\Component\HttpKernel\KernelInterface
     */
    protected $kernel;

    /**
     * @var array
     */
    protected $parameters;

    /**
     * @var EzSystems\BehatBundle\Features\Context\Helpers\ContentManager
     */
    protected $contentManager;

    /**
     * @var array Array to map identifier to urls, should be set by child classes.
     */
    protected $pageIdentifierMap = array();

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
     * Valid Forms, should be set by child classes.
     * Should be defined by key => array( $data, ... )
     * $data = array(
     *      "field1" => "value1",
     *      "field2" => array( "if, "it", "has", "multiple", "values" ),
     *      ...
     *      "fieldN" => "valueN",
     * );
     *
     * @var array
     */
    protected $forms = array();

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
    }

    /**
     * Sets HttpKernel instance.
     * This method will be automatically called by Symfony2Extension ContextInitializer.
     *
     * @param KernelInterface $kernel
     */
    public function setKernel( KernelInterface $kernel )
    {
        $this->kernel = $kernel;
    }

    /**
     * This function will convert Gherkin tables into structure array of data
     *
     * @param \Behat\Gherkin\Node\TableNode|array   $table The Gherkin table to extract the values
     * @param null|array                            $data  If there are values to be concatenated/updated
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
        $rows = $table->getRows();
        array_shift( $rows );   // this is needed to take the first row ( readability only )
        foreach ( $rows as $row )
        {
            $count = count( array_filter( $row ) );
            // check if the field is supposed to be empty
            // or it simply has only 1 element
            if (
                $count == 1
                && count( $row )
                && !method_exists( $table, "getCleanRows" )
            )
            {
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
                if ( count( $aux ) === count( array_keys( $table ) ) )
                    $k = $i;
                else
                    $k = $i +1;

                $key = str_replace( array( '<', '>' ), array( '', '' ), $aux[$k][0] );
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
        return empty( $data )? false : $data;
    }

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
     * Returns valid form data
     *
     * @param  string $form Name of the intended form
     * @return null|array
     *
     * @throws PendingException When the form is not setted
     */
    public function getFormData( $form )
    {
        $form = strtolower( $form );
        if ( isset( $this->forms[$form] ) )
            return $this->forms[$form];

        throw new PendingException( "Data for '$form' form not defined" );
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

        case 'image':
            $data = __DIR__ . "/_fixtures/image.png";
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
     * @Given /^I got "([^"]*)" disabled$/
     */
    public function iGotDisabled( $setting )
    {
        throw new PendingException( "System management" );
    }

    /**
     * @Given /^test is pending(?:| (.+))$/
     */
    public function testIsPendingDesign( $reason )
    {
        throw new PendingException( $reason );
    }
}
