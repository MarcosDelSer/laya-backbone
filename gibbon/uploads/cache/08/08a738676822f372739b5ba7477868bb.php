<?php

use Twig\Environment;
use Twig\Error\LoaderError;
use Twig\Error\RuntimeError;
use Twig\Extension\CoreExtension;
use Twig\Extension\SandboxExtension;
use Twig\Markup;
use Twig\Sandbox\SecurityError;
use Twig\Sandbox\SecurityNotAllowedTagError;
use Twig\Sandbox\SecurityNotAllowedFilterError;
use Twig\Sandbox\SecurityNotAllowedFunctionError;
use Twig\Source;
use Twig\Template;

/* installer/config.twig.html */
class __TwigTemplate_5c948bde8805efc82be71b0a163955e7 extends Template
{
    private $source;
    private $macros = [];

    public function __construct(Environment $env)
    {
        parent::__construct($env);

        $this->source = $this->getSourceContext();

        $this->parent = false;

        $this->blocks = [
        ];
    }

    protected function doDisplay(array $context, array $blocks = [])
    {
        $macros = $this->macros;
        // line 10
        yield "<?php
/*
Gibbon: the flexible, open school platform
Founded by Ross Parker at ICHK Secondary. Built by Ross Parker, Sandra Kuipers and the Gibbon community (https://gibbonedu.org/about/)
Copyright © 2010, Gibbon Foundation
Gibbon™, Gibbon Education Ltd. (Hong Kong)

This program is free software: you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation, either version 3 of the License, or
(at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program.  If not, see <http://www.gnu.org/licenses/>.
*/

/**
 * Sets the database connection information.
 * You can supply an optional \$databasePort if your server requires one.
 */
\$databaseServer = ";
        // line 35
        yield ($context["databaseServer"] ?? null);
        yield ";
\$databaseUsername = ";
        // line 36
        yield ($context["databaseUsername"] ?? null);
        yield ";
\$databasePassword = ";
        // line 37
        yield ($context["databasePassword"] ?? null);
        yield ";
\$databaseName = ";
        // line 38
        yield ($context["databaseName"] ?? null);
        yield ";

/**
 * Sets a globally unique id, to allow multiple installs on a single server.
 */
\$guid = ";
        // line 43
        yield ($context["guid"] ?? null);
        yield ";

/**
 * Sets system-wide caching factor, used to balance performance and freshness.
 * Value represents number of page loads between cache refresh.
 * Must be positive integer. 1 means no caching.
 */
\$caching = 10;

/**
 * Enables the System Admin > Impersonate User action, for testing and development.
 * This option is off by default. Access can be granted by adding usernames to 
 * the list below. Allowed users must also have administrator access.
 */
\$allowImpersonateUser = [];
";
        return; yield '';
    }

    /**
     * @codeCoverageIgnore
     */
    public function getTemplateName()
    {
        return "installer/config.twig.html";
    }

    /**
     * @codeCoverageIgnore
     */
    public function isTraitable()
    {
        return false;
    }

    /**
     * @codeCoverageIgnore
     */
    public function getDebugInfo()
    {
        return array (  85 => 43,  77 => 38,  73 => 37,  69 => 36,  65 => 35,  38 => 10,);
    }

    public function getSourceContext()
    {
        return new Source("{#<!--
Gibbon: the flexible, open school platform
Founded by Ross Parker at ICHK Secondary. Built by Ross Parker, Sandra Kuipers and the Gibbon community (https://gibbonedu.org/about/)
Copyright © 2010, Gibbon Foundation
Gibbon™, Gibbon Education Ltd. (Hong Kong)

This is a Gibbon template file, written in HTML and Twig syntax.
For info about editing, see: https://twig.symfony.com/doc/2.x/
-->#}
<?php
/*
Gibbon: the flexible, open school platform
Founded by Ross Parker at ICHK Secondary. Built by Ross Parker, Sandra Kuipers and the Gibbon community (https://gibbonedu.org/about/)
Copyright © 2010, Gibbon Foundation
Gibbon™, Gibbon Education Ltd. (Hong Kong)

This program is free software: you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation, either version 3 of the License, or
(at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program.  If not, see <http://www.gnu.org/licenses/>.
*/

/**
 * Sets the database connection information.
 * You can supply an optional \$databasePort if your server requires one.
 */
\$databaseServer = {{ databaseServer|raw }};
\$databaseUsername = {{ databaseUsername|raw }};
\$databasePassword = {{ databasePassword|raw }};
\$databaseName = {{ databaseName|raw }};

/**
 * Sets a globally unique id, to allow multiple installs on a single server.
 */
\$guid = {{ guid|raw }};

/**
 * Sets system-wide caching factor, used to balance performance and freshness.
 * Value represents number of page loads between cache refresh.
 * Must be positive integer. 1 means no caching.
 */
\$caching = 10;

/**
 * Enables the System Admin > Impersonate User action, for testing and development.
 * This option is off by default. Access can be granted by adding usernames to 
 * the list below. Allowed users must also have administrator access.
 */
\$allowImpersonateUser = [];
", "installer/config.twig.html", "/var/www/html/gibbon/resources/templates/installer/config.twig.html");
    }
}
