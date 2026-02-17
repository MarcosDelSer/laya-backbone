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

/* installer/install.twig.html */
class __TwigTemplate_ca80683808185765da7d2b7b6af0559c extends Template
{
    private $source;
    private $macros = [];

    public function __construct(Environment $env)
    {
        parent::__construct($env);

        $this->source = $this->getSourceContext();

        $this->blocks = [
            'head' => [$this, 'block_head'],
            'beforePage' => [$this, 'block_beforePage'],
        ];
    }

    protected function doGetParent(array $context)
    {
        // line 11
        return "index.twig.html";
    }

    protected function doDisplay(array $context, array $blocks = [])
    {
        $macros = $this->macros;
        $this->parent = $this->loadTemplate("index.twig.html", "installer/install.twig.html", 11);
        yield from $this->parent->unwrap()->yield($context, array_merge($this->blocks, $blocks));
    }

    // line 13
    public function block_head($context, array $blocks = [])
    {
        $macros = $this->macros;
        // line 14
        yield "    ";
        yield from         $this->loadTemplate("head.twig.html", "installer/install.twig.html", 14)->unwrap()->yieldBlock("meta", $context);
        yield "

    <link rel=\"shortcut icon\" type=\"image/x-icon\" href=\"../favicon.ico\"/>
    <link rel='stylesheet' type='text/css' href='../resources/assets/css/theme.min.css' />
    <link rel='stylesheet' type='text/css' href='../resources/assets/css/core.min.css' />
    <link rel='stylesheet' type='text/css' href='../themes/Default/css/main.css' />
    <script type=\"text/javascript\" src=\"../lib/jquery/jquery.js\"></script>
    <script type=\"text/javascript\" src=\"../lib/jquery/jquery-migrate.min.js\"></script>
    <script type=\"text/javascript\" src=\"../lib/htmx/htmx.min.js\"></script>
    <script type='text/javascript' src='../resources/assets/js/core.js'></script>
    <script type=\"text/javascript\" src=\"../resources/assets/js/installer.js\"></script>
    <script type=\"text/javascript\" src=\"../lib/htmx/alpine.focus.min.js\" defer></script>
    <script type=\"text/javascript\" src=\"../lib/htmx/alpine.validate.min.js\" defer></script>
    <script type=\"text/javascript\" src=\"../lib/htmx/alpine.min.js\" defer></script>
";
        return; yield '';
    }

    // line 30
    public function block_beforePage($context, array $blocks = [])
    {
        $macros = $this->macros;
        // line 31
        yield "
    ";
        // line 32
        if ((($context["step"] ?? null) <= 1)) {
            // line 33
            yield "    <div class=\"shadow bg-white rounded max-w-4xl mx-auto pt-4 pb-6 px-8 mb-6\">
        <h2>";
            // line 34
            yield $this->env->getRuntime('Twig\Runtime\EscaperRuntime')->escape($this->env->getFunction('__')->getCallable()("Welcome To Gibbon"), "html", null, true);
            yield "</h2>

        <p style='padding-top: 7px'>
            ";
            // line 37
            yield $this->env->getRuntime('Twig\Runtime\EscaperRuntime')->escape($this->env->getFunction('__')->getCallable()("Created by teachers, Gibbon is the school platform which solves real problems faced by educators every day."), "html", null, true);
            yield "
            <br/><br/>
            ";
            // line 39
            yield $this->env->getRuntime('Twig\Runtime\EscaperRuntime')->escape($this->env->getFunction('__')->getCallable()("Free, open source and flexible, Gibbon can morph to meet the needs of a huge range of schools."), "html", null, true);
            yield "
            <br/><br/>
            ";
            // line 41
            yield Twig\Extension\CoreExtension::replace($this->env->getFunction('__')->getCallable()("For support, please visit %1\$sgibbonedu.org%2\$s."), ["%1\$s" => "<a target='_blank' href='https://gibbonedu.org/support'>", "%2\$s" => "</a>"]);
            yield "
        </p>
    </div>
    ";
        }
        // line 45
        yield "
";
        return; yield '';
    }

    /**
     * @codeCoverageIgnore
     */
    public function getTemplateName()
    {
        return "installer/install.twig.html";
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
        return array (  108 => 45,  101 => 41,  96 => 39,  91 => 37,  85 => 34,  82 => 33,  80 => 32,  77 => 31,  73 => 30,  52 => 14,  48 => 13,  37 => 11,);
    }

    public function getSourceContext()
    {
        return new Source("", "installer/install.twig.html", "/var/www/html/gibbon/resources/templates/installer/install.twig.html");
    }
}
