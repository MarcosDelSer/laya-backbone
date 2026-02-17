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

/* categories.twig.html */
class __TwigTemplate_d860fcd9118a2e0647171e6193cc6717 extends Template
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
        yield "
";
        // line 11
        if (($context["welcomeText"] ?? null)) {
            // line 12
            yield "    <header class=\"text-base mb-6\">
        ";
            // line 13
            yield ($context["welcomeText"] ?? null);
            yield "
    </header>
";
        }
        // line 16
        yield "
";
        // line 17
        $context['_parent'] = $context;
        $context['_seq'] = CoreExtension::ensureTraversable(($context["categories"] ?? null));
        $context['_iterated'] = false;
        $context['loop'] = [
          'parent' => $context['_parent'],
          'index0' => 0,
          'index'  => 1,
          'first'  => true,
        ];
        if (is_array($context['_seq']) || (is_object($context['_seq']) && $context['_seq'] instanceof \Countable)) {
            $length = count($context['_seq']);
            $context['loop']['revindex0'] = $length - 1;
            $context['loop']['revindex'] = $length;
            $context['loop']['length'] = $length;
            $context['loop']['last'] = 1 === $length;
        }
        foreach ($context['_seq'] as $context["_key"] => $context["category"]) {
            // line 18
            yield "    ";
            $context["viewable"] = (CoreExtension::getAttribute($this->env, $this->source, $context["category"], "viewable", [], "any", false, false, false, 18) == "Y");
            // line 19
            yield "    ";
            $context["hasBackground"] = ( !Twig\Extension\CoreExtension::testEmpty(CoreExtension::getAttribute($this->env, $this->source, $context["category"], "viewableDate", [], "any", false, false, false, 19)) &&  !Twig\Extension\CoreExtension::testEmpty(CoreExtension::getAttribute($this->env, $this->source, $context["category"], "backgroundImage", [], "any", false, false, false, 19)));
            // line 20
            yield "
    <div class=\"w-full h-56 relative rounded mb-6\">
        <div class=\"absolute w-full h-full rounded overflow-hidden\">
            ";
            // line 23
            if (($context["hasBackground"] ?? null)) {
                // line 24
                yield "                <img src=\"";
                yield $this->env->getRuntime('Twig\Runtime\EscaperRuntime')->escape(($context["absoluteURL"] ?? null), "html", null, true);
                yield "/";
                yield $this->env->getRuntime('Twig\Runtime\EscaperRuntime')->escape(CoreExtension::getAttribute($this->env, $this->source, $context["category"], "backgroundImage", [], "any", false, false, false, 24), "html", null, true);
                yield "\" class=\"w-full max-w-full h-full\" style=\"object-position: center; object-fit: cover;\" loading=\"eager\">
            ";
            } else {
                // line 26
                yield "                <div class=\"w-full max-w-full h-full bg-gray-200\"></div>
            ";
            }
            // line 28
            yield "        </div>

        <div class=\"w-2/5 sm:w-1/3 h-full flex flex-col justify-start p-6 rounded-l ";
            // line 30
            yield ((($context["hasBackground"] ?? null)) ? ("") : ("bg-gray-300"));
            yield " bg-blur bg-translucent-gray\">
            <h2 class=\"";
            // line 31
            yield ((($context["hasBackground"] ?? null)) ? ("text-white") : ("text-gray-600"));
            yield " text-base sm:text-2xl mt-0 mb-2\">
                ";
            // line 32
            yield $this->env->getRuntime('Twig\Runtime\EscaperRuntime')->escape(CoreExtension::getAttribute($this->env, $this->source, $context["category"], "name", [], "any", false, false, false, 32), "html", null, true);
            yield "
            </h2>
            
            <div class=\"";
            // line 35
            yield ((($context["hasBackground"] ?? null)) ? ("text-white") : ("text-gray-600"));
            yield " text-xs font-thin\">
                ";
            // line 36
            $context['_parent'] = $context;
            $context['_seq'] = CoreExtension::ensureTraversable(Twig\Extension\CoreExtension::split($this->env->getCharset(), CoreExtension::getAttribute($this->env, $this->source, $context["category"], "eventDates", [], "any", false, false, false, 36), ","));
            $context['loop'] = [
              'parent' => $context['_parent'],
              'index0' => 0,
              'index'  => 1,
              'first'  => true,
            ];
            if (is_array($context['_seq']) || (is_object($context['_seq']) && $context['_seq'] instanceof \Countable)) {
                $length = count($context['_seq']);
                $context['loop']['revindex0'] = $length - 1;
                $context['loop']['revindex'] = $length;
                $context['loop']['length'] = $length;
                $context['loop']['last'] = 1 === $length;
            }
            foreach ($context['_seq'] as $context["_key"] => $context["date"]) {
                // line 37
                yield $this->env->getFunction('formatUsing')->getCallable()("dateReadable", $context["date"], 102);
                // line 38
                yield (( !CoreExtension::getAttribute($this->env, $this->source, $context["loop"], "last", [], "any", false, false, false, 38)) ? (", ") : (""));
                ++$context['loop']['index0'];
                ++$context['loop']['index'];
                $context['loop']['first'] = false;
                if (isset($context['loop']['length'])) {
                    --$context['loop']['revindex0'];
                    --$context['loop']['revindex'];
                    $context['loop']['last'] = 0 === $context['loop']['revindex0'];
                }
            }
            $_parent = $context['_parent'];
            unset($context['_seq'], $context['_iterated'], $context['_key'], $context['date'], $context['_parent'], $context['loop']);
            $context = array_intersect_key($context, $_parent) + $_parent;
            // line 40
            yield "            </div>

            <div class=\"flex-1\">
            </div>

            ";
            // line 45
            if ((($context["viewable"] ?? null) || ($context["canViewInactive"] ?? null))) {
                // line 46
                yield "                <a href=\"";
                yield $this->env->getRuntime('Twig\Runtime\EscaperRuntime')->escape(($context["absoluteURL"] ?? null), "html", null, true);
                yield "/index.php?q=/modules/Activities/explore_category.php&sidebar=false&gibbonActivityCategoryID=";
                yield $this->env->getRuntime('Twig\Runtime\EscaperRuntime')->escape(CoreExtension::getAttribute($this->env, $this->source, $context["category"], "gibbonActivityCategoryID", [], "any", false, false, false, 46), "html", null, true);
                yield "\" class=\"py-2 border border-white hover:bg-translucent transition duration-100 rounded-sm text-center text-white text-base font-light\">
                    ";
                // line 47
                yield $this->env->getRuntime('Twig\Runtime\EscaperRuntime')->escape((((($context["canViewInactive"] ?? null) &&  !($context["viewable"] ?? null))) ? ($this->env->getFunction('__')->getCallable()("Preview")) : ($this->env->getFunction('__')->getCallable()("Explore"))), "html", null, true);
                yield "
                </a>
            ";
            } elseif (($this->extensions['Twig\Extension\CoreExtension']->convertDate() >= $this->extensions['Twig\Extension\CoreExtension']->convertDate(CoreExtension::getAttribute($this->env, $this->source,             // line 49
$context["category"], "endDate", [], "any", false, false, false, 49)))) {
                // line 50
                yield "                <div class=\"";
                yield ((($context["hasBackground"] ?? null)) ? ("text-white font-light") : ("text-gray-600 font-thin"));
                yield " text-center text-white text-base italic\">
                    ";
                // line 51
                yield $this->env->getRuntime('Twig\Runtime\EscaperRuntime')->escape($this->env->getFunction('__')->getCallable()("Past"), "html", null, true);
                yield "
                </div>
            ";
            } else {
                // line 54
                yield "                <div class=\"";
                yield ((($context["hasBackground"] ?? null)) ? ("text-white font-light") : ("text-gray-600 font-thin"));
                yield " text-center text-white text-base italic\">
                    ";
                // line 55
                if ( !Twig\Extension\CoreExtension::testEmpty(CoreExtension::getAttribute($this->env, $this->source, $context["category"], "viewableDate", [], "any", false, false, false, 55))) {
                    // line 56
                    yield "                        ";
                    yield $this->env->getRuntime('Twig\Runtime\EscaperRuntime')->escape($this->env->getFunction('__')->getCallable()("Viewable on"), "html", null, true);
                    yield " ";
                    yield $this->env->getRuntime('Twig\Runtime\EscaperRuntime')->escape($this->extensions['Twig\Extension\CoreExtension']->formatDate(CoreExtension::getAttribute($this->env, $this->source, $context["category"], "viewableDate", [], "any", false, false, false, 56), "M j \\a\\t g:ia"), "html", null, true);
                    yield "
                    ";
                } else {
                    // line 58
                    yield "                        ";
                    yield $this->env->getRuntime('Twig\Runtime\EscaperRuntime')->escape($this->env->getFunction('__')->getCallable()("Upcoming"), "html", null, true);
                    yield "
                    ";
                }
                // line 60
                yield "                </div>
            ";
            }
            // line 62
            yield "            
        </div>
        
    </div>

";
            $context['_iterated'] = true;
            ++$context['loop']['index0'];
            ++$context['loop']['index'];
            $context['loop']['first'] = false;
            if (isset($context['loop']['length'])) {
                --$context['loop']['revindex0'];
                --$context['loop']['revindex'];
                $context['loop']['last'] = 0 === $context['loop']['revindex0'];
            }
        }
        if (!$context['_iterated']) {
            // line 68
            yield "    <div class=\"h-48 rounded-sm border bg-gray-100 overflow-hidden\">
        ";
            // line 69
            yield from             $this->loadTemplate("components/blankSlate.twig.html", "categories.twig.html", 69)->unwrap()->yield(CoreExtension::merge($context, ["blankSlate" => $this->env->getFunction('__')->getCallable()("There are no activities available yet. Check back soon!")]));
            // line 70
            yield "    </div>
";
        }
        $_parent = $context['_parent'];
        unset($context['_seq'], $context['_iterated'], $context['_key'], $context['category'], $context['_parent'], $context['loop']);
        $context = array_intersect_key($context, $_parent) + $_parent;
        // line 72
        yield "
";
        return; yield '';
    }

    /**
     * @codeCoverageIgnore
     */
    public function getTemplateName()
    {
        return "categories.twig.html";
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
        return array (  242 => 72,  235 => 70,  233 => 69,  230 => 68,  212 => 62,  208 => 60,  202 => 58,  194 => 56,  192 => 55,  187 => 54,  181 => 51,  176 => 50,  174 => 49,  169 => 47,  162 => 46,  160 => 45,  153 => 40,  139 => 38,  137 => 37,  120 => 36,  116 => 35,  110 => 32,  106 => 31,  102 => 30,  98 => 28,  94 => 26,  86 => 24,  84 => 23,  79 => 20,  76 => 19,  73 => 18,  55 => 17,  52 => 16,  46 => 13,  43 => 12,  41 => 11,  38 => 10,);
    }

    public function getSourceContext()
    {
        return new Source("", "categories.twig.html", "/var/www/html/gibbon/modules/Activities/templates/categories.twig.html");
    }
}
