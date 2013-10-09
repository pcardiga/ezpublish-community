<?php
/**
 * File containing the InterfaceHelperContext class.
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

/**
 * Interface helper context (front end).
 */
class InterfaceHelperContext extends BehatContext
{
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
    protected function fillForm( $formData, $onlyListed = null )
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


/******************************************************************************
 * **************************        WHEN          ************************** *
 ******************************************************************************/

/******************************************************************************
 * **************************        THEN          ************************** *
 ******************************************************************************/
}