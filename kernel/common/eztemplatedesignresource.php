<?php
//
// Definition of eZTemplatedesignresource class
//
// Created on: <14-Sep-2002 15:37:17 amos>
//
// Copyright (C) 1999-2002 eZ systems as. All rights reserved.
//
// This source file is part of the eZ publish (tm) Open Source Content
// Management System.
//
// This file may be distributed and/or modified under the terms of the
// "GNU General Public License" version 2 as published by the Free
// Software Foundation and appearing in the file LICENSE.GPL included in
// the packaging of this file.
//
// Licencees holding valid "eZ publish professional licences" may use this
// file in accordance with the "eZ publish professional licence" Agreement
// provided with the Software.
//
// This file is provided AS IS with NO WARRANTY OF ANY KIND, INCLUDING
// THE WARRANTY OF DESIGN, MERCHANTABILITY AND FITNESS FOR A PARTICULAR
// PURPOSE.
//
// The "eZ publish professional licence" is available at
// http://ez.no/home/licences/professional/. For pricing of this licence
// please contact us via e-mail to licence@ez.no. Further contact
// information is available at http://ez.no/home/contact/.
//
// The "GNU General Public License" (GPL) is available at
// http://www.gnu.org/copyleft/gpl.html.
//
// Contact licence@ez.no if any conditions of this licencing isn't clear to
// you.
//

/*! \file eztemplatedesignresource.php
*/

/*!
  \class eZTemplatedesignresource eztemplatedesignresource.php
  \brief Handles template file loading with override support

*/

include_once( "lib/eztemplate/classes/eztemplatefileresource.php" );
include_once( "lib/ezutils/classes/ezini.php" );

class eZTemplateDesignResource extends eZTemplateFileResource
{
    /*!
     Initializes with a default resource name "design".
    */
    function eZTemplateDesignResource( $name = "design" )
    {
        $this->eZTemplateFileResource( $name );
        $this->Keys = array();
    }

    /*!
     Loads the template file if it exists, also sets the modification timestamp.
     Returns true if the file exists.
    */
    function handleResource( &$tpl, &$text, &$tstamp, &$path, $method, &$extraParameters )
    {
        $ini =& eZINI::instance();
        $standardBase = $ini->variable( "DesignSettings", "StandardDesign" );
        $siteBase = $ini->variable( "DesignSettings", "SiteDesign" );

        $matches = array();
        $matches[] = array( "file" => "design/$siteBase/override/templates/$path",
                            "type" => "override" );
        $matches[] = array( "file" => "design/$standardBase/override/templates/$path",
                            "type" => "override" );
        $matches[] = array( "file" => "design/$siteBase/templates/$path",
                            "type" => "normal" );
        $matches[] = array( "file" => "design/$standardBase/templates/$path",
                            "type" => "normal" );

        $matchKeys = $this->Keys;
        $matchedKeys = array();

        if ( is_array( $extraParameters ) and
             isset( $extraParameters['ezdesign:keys'] ) )
        {
            $this->mergeKeys( $matchKeys, $extraParameters['ezdesign:keys'] );
        }

        $match = null;
        foreach ( $matches as $templateMatch )
        {
            $templatePath = $templateMatch["file"];
            $templateType = $templateMatch["type"];
            if ( $templateType == "normal" )
            {
                if ( file_exists( $templatePath ) )
                {
                    $match = $templateMatch;
                    break;
                }
            }
            else if ( $templateType == "override" )
            {
                if ( count( $matchKeys ) == 0 )
                    continue;
                $templateDir = false;
                if ( preg_match( "#^(.+)/(.+)(\.tpl)$#", $templatePath, $regs ) )
                {
                    $templateDir = $regs[1] . "/" . $regs[2];
                }
                $foundOverrideFile = false;
                if ( !$foundOverrideFile ) // Check for dir/filebase_keyname_keyid.tpl, eg. content/view_section_1.tpl
                {
                    preg_match( "#^(.+)/(.+)(\.tpl)$#", $templatePath, $regs );
                    foreach ( $matchKeys as $matchKeyName => $matchKeyValue )
                    {
                        $file = $regs[1] . "/" . $regs[2] . "_$matchKeyName" . "_$matchKeyValue" . $regs[3];
                        if ( file_exists( $file ) )
                        {
                            $match = $templateMatch;
                            $match["file"] = $file;
                            $foundOverrideFile = true;
                            $matchedKeys[$matchKeyName] = $matchKeyValue;
//                             eZDebug::writeNotice( "Match found, using override " . $match["file"]  );
                            break;
                        }
                    }
                }
                if ( $match !== null )
                    break;
            }
        }
        if ( $match === null )
            return false;

        $file = $match["file"];

        $usedKeys = array();
        foreach ( $matchKeys as $matchKeyName => $matchKeyValue )
        {
            $usedKeys[$matchKeyName] = $matchKeyValue;
        }
        $extraParameters['ezdesign:used_keys'] = $usedKeys;
        $extraParameters['ezdesign:matched_keys'] = $matchedKeys;
        $tpl->setVariable( 'used', $usedKeys, 'DesignKeys' );
        $tpl->setVariable( 'matched', $matchedKeys, 'DesignKeys' );
        return eZTemplateFileResource::handleResource( $tpl, $text, $tstamp, $file, $method, $extraParameters );
    }

    /*!
     Sets the override keys to \a $keys, if some of the keys already exists they are overriden
     by the new keys.
     \sa clearKeys
    */
    function setKeys( $keys )
    {
        $this->mergeKeys( $this->Keys, $keys );
    }

    /*!
     \private
     Merges keys set in \a $keys with the array in \a $originalKeys.
    */
    function mergeKeys( &$originalKeys, $keys )
    {
        foreach ( $keys as $key )
        {
            if ( count( $key ) >= 2 )
                $originalKeys[$key[0]] = $key[1];
        }
    }

    /*!
     Removes all override keys.
     \sa setKeys
    */
    function clearKeys()
    {
        $this->Keys = array();
    }

    /*!
     \return the unique instance of the design resource.
    */
    function &instance()
    {
        $instance =& $GLOBALS["eZTemplateDesignResourceInstance"];
        if ( get_class( $instance ) != "eztemplatedesignresource" )
        {
            $instance = new eZTemplateDesignResource();
        }
        return $instance;
    }

    var $Keys;
}

?>
