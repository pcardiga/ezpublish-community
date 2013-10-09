<?php
/**
 * File containing the ContentManager class.
 *
 * This class contains general feature context for Behat.
 *
 * @copyright Copyright (C) 1999-2013 eZ Systems AS. All rights reserved.
 * @license http://www.gnu.org/licenses/gpl-2.0.txt GNU General Public License v2
 * @version //autogentag//
 */

namespace EzSystems\BehatBundle\Features\Context;

use Behat\Behat\Context\Step;
use Behat\Behat\Context\BehatContext;
use Behat\Mink\Exception\UnsupportedDriverActionException as MinkUnsupportedDriverActionException;
use Behat\Symfony2Extension\Context\KernelAwareInterface;
//use PHPUnit_Framework_Assert as Assertion;
//use Symfony\Component\HttpKernel\KernelInterface;

/**
 * Content manager helper.
 */
class ContentManager
{
    /**
     * @var array Content identifiers holder
     */
    private $contentHolder = array();

    /**
     * @var string Default language identifier
     */
    private $lang = "eng-GB";

    /**
     * @var array Demanded values for possible data omission
     */
    private $demanded = array (
        "FieldDefinitionCreateStruct" => array(),
        "ContentTypeCreateStruct" => array(
            "identifier"          => "unique",
            "names"               => "array:language:string",
            "mainLanguageCode"    => "language",
        ),
        "Content"                => array(),
    );

    /**
     * @var array
     */
    private $parameters;

    /**
     * Is alias verifies if the identifier passed is a single alpha character
     * or not, and if it is returns the respective object.
     * For simple Gherkin writing the objects can have identifiers like 'A', 'P'
     * so that doesn't have to have a complete word, however this single character
     * doesn't mean much, so it returns the respective object
     * @param string $identifier Identifier passed through Gherkin
     * @return boolean
     */
    protected function isAlias( $identifier )
    {
        if( strlen( $identifier ) == 1
                && $identifier >= 'A'
                && $identifier <= 'Z'
        )
            return $this->contentHolder[$identifier];
    }

    /**
     * Verify if parameter has multi data
     * ( ex: "this_is:a_multidata:string", "container:true", "date:24-01-1982" )
     * @param  string $string
     * @return array
     */
    private function hasMultiData( $string )
    {
        $aux = explode( ':', $string );
        if( count( $aux ) > 1 )
            return $aux;

        return $string;
    }

    /**
     * The fill demanded porpouse is to fill the omited values during the tests and are required
     *      for the object to be created
     * ( it should be checked if it is intended to fill demanded on the previous function )
     * @param object &$object    This object can be any object (ex: Group, User, ...)
     * @param object $objectType This is the string identifier of the object above (ex: "ContentTypeCreateStruct")
     */
    private function fillDemanded( &$object, $objectType )
    {
        foreach( $this->demanded[$objectType] as $field => $value )
        {
            if( empty( $object->{$field} ) )
            {
                switch( $value ) {
                case 'unique':
                    $object->{$field} = "unique" . rand( 1000, 9999 );
                    break;
                case 'array:language:string':
                    $object->{$field} = array( $this->lang => "string" . rand( 1000, 9999 ) );
                    break;
                case 'language':
                    $object->{$field} = $this->lang;
                    break;
                }
            }
        }
    }

    /**
     * Sets the definitions for the ContentTypes and FieldDefinitions
     * ( a cell can have the definition and the value
     * ex: "definition:value", "searcheable:true", "publishDate:12-01-2010", ...
     * see hasMultiData() for more info )
     * @param ContentTypeCreateStruct|FieldDefinitionCreateStruct $object
     * @param array                                               $definitions
     */
    private function setDefinitions( &$object, array $definitions )
    {
        // set definitions
        foreach( $definitions as $def )
        {
            $value = true;

            // redefine if it is multidata
            $aux = $this->hasMultiData( $def );
            if(is_array( $aux ) ) {
                $def = $aux[0];
                $value = $aux[1];
            }

            // verify if it is boolean in the definition
            switch( strtolower( $value ) ) {
            case 'false': $value = false; break;
            case 'true':  $value = true;  break;
            }
            // or if the boolean state is in the definition
            if( ( $aux = str_replace( 'not ', '', $def ) ) !== $def ) {
                $value = false;
                $def = $aux;
            }

            // check if property was passed correctly
            if( !property_exists( $object, $def ) )
            {
                // possible attempts for the field
                $tmp = array(
                    strtolower( $def ),
                    "is" . ucfirst( strtolower( $def ) ),
                    strtolower( $def ) . "Id",
                    strtolower( $def ) . "s",
                );

                foreach( $tmp as $field )
                {
                    if( property_exists( $object, $field ) ) {
                        $def = $field;
                        break;
                    }
                }
            }

            // and finaly define the value
            $object->{$def} = $value;
        }
    }

    /**
     * Creates a FieldDefinitionCreateStruct
     * @param  string  $identifier Identifier of the Field Definition (must be unique)
     * @param  string  $fieldtype  Field Definition type identifier
     * @param  array   $definitions Definitions of the Field Definition
     * @param  boolean $fillDemanded This value is for filling (or not) the required and omited fields
     * @return FieldDefinitionCreateStruct
     */
    private function createFieldDefinition( $identifier, $fieldtype, array $definitions, $fillDemanded = true )
    {
        // get repositories
        $repository = $this->kernel->getContainer()->get( 'ezpublish.api.repository' );
        $contentTypeService = $repository->getContentTypeService();

        $fieldDefinition = $contentTypeService->newFieldDefinitionCreateStruct( $identifier, $fieldtype );

        // set definitions
        $this->setDefinitions( $fieldDefinition, $definitions );

        if( $fillDemanded )
            $this->fillDemanded ( $fieldDefinition , 'FieldDefinitionCreateStruct' );

        return $fieldDefinition;
    }

    /**
     * Creates a Content Type Draft
     * @param  string  $identifier   Identifier for the Content Type (must be unique)
     * @param  array   $definitions  Array of other definitions
     * @param  array   $fields       Array of fields and their definitions
     * @param  array   $groups       Array of groups where the Content Type must be created
     * @param  boolean $fillDemanded This value is for filling (or not) the required and omited fields
     * @return ContentTypeDraft
     */
    private function createContentTypeDraft( $identifier, array $definitions, array $fields, array $groups, $fillDemanded = true )
    {
        // get repositories
        $repository = $this->kernel->getContainer()->get( 'ezpublish.api.repository' );
        $contentTypeService = $repository->getContentTypeService();

        // create content type
        $create = $contentTypeService->newContentTypeCreateStruct( $identifier );

        // set definitions
        $this->setDefinitions( $create, $definitions );

        // set fields
        foreach( $fields as $field )
        {
            $tmp = $this->createFieldDefinition( $field[1], $field[0], array_splice( $field, 2 ), $fillDemanded );
            $create->addFieldDefinition( $tmp );
        }

        // verify data and fill if its to fillDemanded
        if( $fillDemanded )
            $this->fillDemanded( $create, 'ContentTypeCreateStruct' );

        if( empty( $groups ) && $fillDemanded )
            $groups = array( 1 );

        // load groups
        $contentTypeGroups = array();
        foreach( $groups as $group )
        {
            if( is_integer( $group ) )
                $contentTypeGroups[] = $contentTypeService->loadContentTypeGroup( $group );
            else
                $contentTypeGroups[] = $contentTypeService->loadContentTypeGroupByIdentifier( $group );
        }

        // finaly create draft
        $draft = $contentTypeService->createContentType( $create, $contentTypeGroups );

        // return the draft
        return $draft;
    }

    /**
     * Publish a ContentType Draft
     * @param  ContentTypeDraft $draft This is the draft to be published
     * @return ContentType
     */
    private function publishContentType( ContentTypeDraft $draft )
    {
        return $this->kernel->getContainer()
                ->get( 'ezpublish.api.repository' )
                ->getContentTypeService()
                ->publishContentTypeDraft( $draft );
    }

    /**
     * Create a Content Draft of a specific Content Type
     * @param string     $contentType  This is the Content Type identifier
     * @param string     $mainLanguage This is the language code (ex: eng-GB)
     * @param int|string $location     Location can be int (id of the location) or path for the location
     * @param array $fields
     *     $fields = array(
     *          'fieldIdentifierInContentType' => array( //ex: name, title, ...
     *              'FieldType' => 'fieldTypeIdentifier',
     *              'data'      => field_specific_data_here,
     *          ),
     *          'description'   => array(
     *              'FieldType' => 'ezxml',
     *              'data'      => '<paragraph>this is a paragraph</paragraph>',
     *          )
     *     )
     * @param boolean   $fillDemanded  This value is for filling (or not) the required and omited fields
     */
    private function createContentDraft( $contentType, $mainLanguage, $location, array $fields, $fillDemanded = true )
    {
        // get repositories
        $repository = $this->kernel->getContainer()->get( 'ezpublish.api.repository' );
        $contentTypeService = $repository->getContentTypeService();
        $contentService = $repository->getContentService();
        $locationService = $repository->getLocationService();

        // check main language
        if( empty( $mainLanguage ) && $fillDemanded )
            $mainLanguage = $this->lang;

        // check Content Type
        if( is_integer( $contentType ) )
            $contentTypeChecked = $contentTypeService->loadContentType( $contentType );
        else if( is_string( $contentType ) )
            $contentTypeChecked = $contentTypeService->loadContentTypeByIdentifier( $contentType );
        else
            $contentTypeChecked = $contentType;

        // create ContentCreateStruct
        $struct = $contentService->newContentCreateStruct( $contentTypeChecked, $mainLanguage );

        // set fields
        foreach( $fields as $fieldtype => $data )
        {
            $this->setContentField( $struct, $fieldtype, $data['FieldType'], $data['data'] );
        }

        // get the new location
        if( empty( $location ) && $fillDemanded )
            $location = 2;

        if( is_integer( $location ) )
            $newLocation = $locationService->newLocationCreateStruct( 2 );
        else if( is_string( $location ) ) {
            $urlAliasService = $repository->getUrlAliasService();
            if( $location[0] != '/' )
                $location = "/$location";

            $aux = $urlAliasService->lookup( $location );
            $newLocation = $locationService->newLocationCreateStruct( $aux->destination );
        }
        else
            $newLocation = $location;

        // finaly create draft
        $contentService->createContent( $struct, $newLocation );
    }

    /**
     * Set field to a ContentCreateStruct or ContentUpdateStruct
     * @param  ContentCreateStruct|ContentUpdateStruct $object     The object to whom the fields will be setted
     * @param  string                                  $identifier Field identifier ( on Content Type
     * @param  string                                  $fieldtype  Field Type identifier
     * @param  mixed                                   $data       This is the data to be setted to field
     * @throws PendingException
     *
     * @todo Implement all fields
     */
    private function setContentField( &$object, $identifier, $fieldtype, $data )
    {
        switch( $fieldtype ) {
        // 5.x fields
        case 'ezauthor':
        case 'ezbinaryfile':
        case 'ezcountry':
        case 'ezdate':
        case 'ezdatetime':
        case 'ezgmaplocation':
        case 'ezimage':
        case 'ezmedia':
        case 'ezobjectrelation':
        case 'ezobjectrelationlist':
        case 'ezselection':
        case 'ezsrrating':
        case 'ezuser':
        case 'ezxmltext':
        case 'ezpage':
        case 'eztime':

        // legacy fields
        case 'identifier':
        case 'isbn':
        case 'matrix':
        case 'multi-option2':
        case 'product catgory':
        case 'multi-price':
        case 'option':
        case 'price':
        case 'range option':
            throw new PendingException( "Fildtype '$fieldtype' not defined yet." );
            break;

        case 'ezemail':
        case 'ezfloat':
        case 'ezinteger':
        case 'ezkeyword':
        case 'ezstring':
        case 'eztext':
        case 'ezurl':
            $object->setField( $identifier, $data );
            break;
        }
    }

    /**
     * Publish a Content Version
     * @param  VersionInfo $vInfo This is the version info to be published
     * @return Content
     */
    private function publishContentVersion( VersionInfo $vInfo )
    {
        return $this->kernel->getContainer()
                ->get( 'ezpublish.api.repository' )
                ->getContentService()
                ->publishVersion( $vInfo );
    }

    /**
     * Converts the Gherkin table to a default array to be use in create/updateContentDraft
     * @param  string $contentTypeIdentifier Identifies the Content Type to be used
     * @param  array $data                   This has all user input data
     * @return array
     *     $results = array(
     *          'fieldIdentifierInContentType' => array( //ex: name, title, ...
     *              'FieldType' => 'fieldTypeIdentifier',
     *              'data'      => field_specific_data_here,
     *          ),
     *          'description'   => array(
     *              'FieldType' => 'ezxml',
     *              'data'      => '<paragraph>this is a paragraph</paragraph>',
     *          )
     *     )
     * @throws \InvalidArgumentException When is single array and the number of fields is not the same as the total of values
     */
    private function makeFieldsArrayForContent( $contentTypeIdentifier, $data )
    {
        // get repositories
        $repository = $this->kernel->getContainer()->get( 'ezpublish.api.repository' );
        $contentTypeService = $repository->getContentTypeService();

        // get the Content Type
        $contentType = $contentTypeService->loadContentTypeByIdentifier( $contentTypeIdentifier );

        // get the fields from the Content Type
        $fieldsDefinitions = $contentType->getFieldDefinitions();

        $result = array();
        // now verify if fields are described as a field-value array:
        // array (
        //     array( field1, value1),
        //     array( field2, value2),
        //     ...
        // )
        if( count( $data ) > 1 ) {
            foreach( $data as $field => $value )
            {
                foreach( $fieldsDefinitions as $fieldDefinition )
                {
                    if( $fieldDefinition->identifier === $field || $fieldDefinition->identifier === strtolower( $field ) )
                    {
                        //verify if it is the first value for this field
                        if( !isset( $result[$fieldDefinition->identifier] ) )
                            $result[$fieldDefinition->identifier] = array(
                                'FieldType' => $fieldDefinition->fieldTypeIdentifier,
                                'data' => array()
                            );

                        // add value
                        $result[$tmp]['data'][] = $value;
                        break;
                    }
                }
            }
        }
        // or if the field name is not present (only 1 array with all data):
        // array( value1, value2, ..., valueN )
        else {
            // verify if the total of values is equal to total of fields
            if( ( $count = count( $data ) ) !== count( $fieldsDefinitions ) )
                throw new \InvalidArgumentException( "Table have different amount of values than the Content Type fields." );

            for( $i = 0 ; $count > $i ; $i++ )
            {
                // set the field
                $results[$fieldsDefinitions[$i]->identifier] = array(
                    'FieldType' => $fieldDefinition[$i]->fieldTypeIdentifier,
                    'data' => array( $data[$i] )
                );
            }
        }

        // return data
        return $results;
    }
}