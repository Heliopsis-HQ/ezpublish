<?php
//
// Created on: <09-May-2003 10:44:02 bf>
//
// Copyright (C) 1999-2003 eZ systems as. All rights reserved.
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
// http://ez.no/products/licences/professional/. For pricing of this licence
// please contact us via e-mail to licence@ez.no. Further contact
// information is available at http://ez.no/home/contact/.
//
// The "GNU General Public License" (GPL) is available at
// http://www.gnu.org/copyleft/gpl.html.
//
// Contact licence@ez.no if any conditions of this licencing isn't clear to
// you.
//

$http =& eZHTTPTool::instance();
$module =& $Params["Module"];
$parameters =& $Params["Parameters"];

include_once( "kernel/common/template.php" );
include_once( "kernel/common/eztemplatedesignresource.php" );
include_once( 'lib/ezutils/classes/ezhttptool.php' );
include_once( "kernel/classes/ezcontentclass.php" );

$ini =& eZINI::instance();
$tpl =& templateInit();

// Todo: read from siteaccess settings
$siteAccess = $http->sessionVariable( 'eZTemplateAdminCurrentSiteAccess' );

$siteBase = $siteAccess;

$template = "";
foreach ( $parameters as $param )
{
    $template .= "/$param";
}

if ( $module->isCurrentAction( 'CreateOverride' ) )
{
    $templateName = trim( $http->postVariable( 'TemplateName' ) );

    if ( trim( $templateName ) != "" )
    {
        $templateName = trim( $http->postVariable( 'TemplateName' ) );

        $matchArray = $http->postVariable( 'Match' );

        $classID = $matchArray['class'];

        $class = eZContentClass::fetch( $classID );
        // Check what kind of contents we should create in the template
        switch ( $http->postVariable( 'TemplateContent' ) )
        {
            case 'DefaultCopy' :
            {
                $overrideArray =& eZTemplatedesignresource::overrideArray();
                $fileName = $overrideArray[$template]['base_dir'] . $overrideArray[$template]['template'];
                $fp = fopen( $fileName, 'r' );
                if ( $fp )
                {
                    $templateCode = fread( $fp, filesize( $fileName ) );
                }
                else
                {
                    print( "Could not open file" );
                }
                fclose( $fp );
            }break;

            case 'ContainerTemplate' :
            {
                $templateCode = "<h1>{\$node.name}</h1>\n\n";

                // Append attribute view
                if ( get_class( $class ) == "ezcontentclass" )
                {
                    $attributes =& $class->fetchAttributes();
                    foreach ( $attributes as $attribute )
                    {
                        $identifier = $attribute->attribute( 'identifier' );
                        $name = $attribute->attribute( 'name' );
                        $templateCode .= "<h2>$name</h2>\n";
                        $templateCode .= "{attribute_view_gui attribute=\$node.object.data_map.$identifier}\n\n";
                    }
                }

                $templateCode .= "" .
                     "{let page_limit=20\n" .
                     "    children=fetch('content','list',hash(parent_node_id,\$node.node_id,sort_by,\$node.sort_array,limit,\$page_limit,offset,\$view_parameters.offset))" .
                     "    list_count=fetch('content','list_count',hash(parent_node_id,\$node.node_id))}\n" .
                     "\n" .
                     "{section name=Child loop=\$children sequence=array(bglight,bgdark)}\n" .
                     "{node_view_gui view=line content_node=\$Child:item}\n" .
                     "{/section}\n" .

                     "{include name=navigator\n" .
                     "    uri='design:navigator/google.tpl'\n" .
                     "    page_uri=concat('/content/view','/full/',\$node.node_id)\n" .
                     "    item_count=\$list_count\n" .
                     "    view_parameters=\$view_parameters\n" .
                     "    item_limit=\$page_limit}\n";
                     "{/let}\n";
            }break;

            case 'ViewTemplate' :
            {
                $templateCode = "<h1>{\$node.name}</h1>\n\n";

                // Append attribute view
                if ( get_class( $class ) == "ezcontentclass" )
                {
                    $attributes =& $class->fetchAttributes();
                    foreach ( $attributes as $attribute )
                    {
                        $identifier = $attribute->attribute( 'identifier' );
                        $name = $attribute->attribute( 'name' );
                        $templateCode .= "<h2>$name</h2>\n";
                        $templateCode .= "{attribute_view_gui attribute=\$node.object.data_map.$identifier}\n\n";
                    }
                }

            }break;

            default:
            case 'EmptyFile' :
            {
            }break;
        }

        $fileName = "design/$siteBase/override/templates/" . $templateName . ".tpl";
        $fp = fopen( $fileName, "w+" );
        if ( $fp )
        {
            fwrite( $fp, $templateCode );
            fclose( $fp );

            // Store override.ini.append file
            $overrideINI = eZINI::instance( 'override.ini', 'settings', null, null, true );
            $overrideINI->prependOverrideDir( "siteaccess/$siteAccess", false, 'siteaccess' );
            $overrideINI->loadCache();

            $templateFile = preg_replace( "#^/(.*)$#", "\\1", $template );

            $overrideINI->setVariable( $templateName, 'Source', $templateFile );
            $overrideINI->setVariable( $templateName, 'MatchFile', $templateName . ".tpl" );
            $overrideINI->setVariable( $templateName, 'Subdir', "templates" );

            foreach ( array_keys( $matchArray ) as $matchKey )
            {
                if ( $matchArray[$matchKey] == -1 )
                    unset( $matchArray[$matchKey] );
            }

            $overrideINI->setVariable( $templateName, 'Match', $matchArray );

            $overrideINI->save( "siteaccess/$siteAccess/override.ini.append" );

            // Expire content cache
            include_once( 'lib/ezutils/classes/ezexpiryhandler.php' );
            $handler =& eZExpiryHandler::instance();
            $handler->setTimestamp( 'content-cache', mktime() );
            $handler->store();

            // Clear override cache
            $cachedDir = "var/cache/override/";
            eZDir::recursiveDelete( $cachedDir );
        }
        else
        {
            eZDebug::writeError( "Could not create override template, check permissions on $fileName", "Template override" );
        }



        $module->redirectTo( '/setup/templateview'. $template );
        return EZ_MODULE_HOOK_STATUS_CANCEL_RUN;
    }
    else
    {
        print( "Empty name" );
    }
}

$templateType = 'default';
if ( strpos( $template, "node/view" ) )
{
    $templateType = 'node_view';
}
else if ( strpos( $template, "content/view" ) )
{
    $templateType = 'content_view';
}
else if ( strpos( $template, "pagelayout.tpl" ) )
{
    $templateType = 'pagelayout';
}


$tpl->setVariable( 'template', $template );
$tpl->setVariable( 'template_type', $templateType );
$tpl->setVariable( 'template_name', $templateName );
$tpl->setVariable( 'site_base', $siteBase );

$Result = array();
$Result['content'] =& $tpl->fetch( "design:setup/templatecreate.tpl" );
$Result['path'] = array( array( 'url' => "/setup/templatelist/",
                                'text' => ezi18n( 'kernel/setup', 'Template list' ) ),
                         array( 'url' => "/setup/templateview". $template,
                                'text' => ezi18n( 'kernel/setup', 'Template view' ) ),
                         array( 'url' => false,
                                'text' => ezi18n( 'kernel/setup', 'Create new template' ) ) );
?>
