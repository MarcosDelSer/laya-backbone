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

/* components/formSearch.twig.html */
class __TwigTemplate_5bb927074e00a6fca766f7668f8ce13d extends Template
{
    private $source;
    private $macros = [];

    public function __construct(Environment $env)
    {
        parent::__construct($env);

        $this->source = $this->getSourceContext();

        $this->parent = false;

        $this->blocks = [
            'header' => [$this, 'block_header'],
        ];
    }

    protected function doDisplay(array $context, array $blocks = [])
    {
        $macros = $this->macros;
        // line 10
        yield "    
<form x-validate ";
        // line 11
        yield CoreExtension::getAttribute($this->env, $this->source, ($context["form"] ?? null), "getAttributeString", [], "any", false, false, false, 11);
        yield " x-data=\"{'advancedOptions': ";
        yield $this->env->getRuntime('Twig\Runtime\EscaperRuntime')->escape(((CoreExtension::getAttribute($this->env, $this->source, ($context["form"] ?? null), "getData", ["advanced-options"], "method", true, true, false, 11)) ? (Twig\Extension\CoreExtension::default(CoreExtension::getAttribute($this->env, $this->source, ($context["form"] ?? null), "getData", ["advanced-options"], "method", false, false, false, 11), "false")) : ("false")), "html", null, true);
        yield ", 'invalid': false, 'submitting': false}\"  x-ref=\"form\" @submit=\"\$validate.submit; invalid = !\$validate.isComplete(\$el); if (invalid) submitting = false;\" @change.debounce.750ms=\"if (invalid) invalid = !\$validate.isComplete(\$el); \">

    ";
        // line 13
        if ((CoreExtension::getAttribute($this->env, $this->source, ($context["form"] ?? null), "getTitle", [], "any", false, false, false, 13) &&  !CoreExtension::getAttribute($this->env, $this->source, ($context["form"] ?? null), "getHeader", [], "any", false, false, false, 13))) {
            // line 14
            yield "        <h2>";
            yield $this->env->getRuntime('Twig\Runtime\EscaperRuntime')->escape(CoreExtension::getAttribute($this->env, $this->source, ($context["form"] ?? null), "getTitle", [], "any", false, false, false, 14), "html", null, true);
            yield "</h2>
    ";
        }
        // line 16
        yield "
    ";
        // line 17
        if (CoreExtension::getAttribute($this->env, $this->source, ($context["form"] ?? null), "getDescription", [], "any", false, false, false, 17)) {
            // line 18
            yield "        <p>";
            yield CoreExtension::getAttribute($this->env, $this->source, ($context["form"] ?? null), "getDescription", [], "any", false, false, false, 18);
            yield "</p>
    ";
        }
        // line 20
        yield "    
    ";
        // line 21
        yield from $this->unwrap()->yieldBlock('header', $context, $blocks);
        // line 33
        yield "
    ";
        // line 34
        $context['_parent'] = $context;
        $context['_seq'] = CoreExtension::ensureTraversable(CoreExtension::getAttribute($this->env, $this->source, ($context["form"] ?? null), "getHiddenValues", [], "any", false, false, false, 34));
        foreach ($context['_seq'] as $context["_key"] => $context["values"]) {
            // line 35
            yield "        <input type=\"hidden\" name=\"";
            yield $this->env->getRuntime('Twig\Runtime\EscaperRuntime')->escape(CoreExtension::getAttribute($this->env, $this->source, $context["values"], "name", [], "any", false, false, false, 35), "html", null, true);
            yield "\" value=\"";
            yield $this->env->getRuntime('Twig\Runtime\EscaperRuntime')->escape(CoreExtension::getAttribute($this->env, $this->source, $context["values"], "value", [], "any", false, false, false, 35), "html", null, true);
            yield "\">
    ";
        }
        $_parent = $context['_parent'];
        unset($context['_seq'], $context['_iterated'], $context['_key'], $context['values'], $context['_parent'], $context['loop']);
        $context = array_intersect_key($context, $_parent) + $_parent;
        // line 37
        yield "
    ";
        // line 38
        if ((Twig\Extension\CoreExtension::length($this->env->getCharset(), CoreExtension::getAttribute($this->env, $this->source, ($context["form"] ?? null), "getRows", [], "any", false, false, false, 38)) > 0)) {
            // line 39
            yield "    <section class=\"w-full bg-blue-50 border rounded-md px-4 py-2\">

        ";
            // line 41
            $context['_parent'] = $context;
            $context['_seq'] = CoreExtension::ensureTraversable(CoreExtension::getAttribute($this->env, $this->source, ($context["form"] ?? null), "getRows", [], "any", false, false, false, 41));
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
            foreach ($context['_seq'] as $context["num"] => $context["row"]) {
                // line 42
                yield "
            ";
                // line 43
                $context["rowLoop"] = $context["loop"];
                // line 44
                yield "            ";
                $context["rowClass"] = "flex flex-col sm:flex-row justify-between content-center p-0 gap-2 sm:gap-6 sm:py-2";
                // line 45
                yield "
            <div id=\"";
                // line 46
                yield $this->env->getRuntime('Twig\Runtime\EscaperRuntime')->escape(CoreExtension::getAttribute($this->env, $this->source, $context["row"], "getID", [], "any", false, false, false, 46), "html", null, true);
                yield "\" class=\"";
                yield $this->env->getRuntime('Twig\Runtime\EscaperRuntime')->escape(CoreExtension::getAttribute($this->env, $this->source, $context["row"], "getClass", [], "any", false, false, false, 46), "html", null, true);
                yield " ";
                yield $this->env->getRuntime('Twig\Runtime\EscaperRuntime')->escape(($context["rowClass"] ?? null), "html", null, true);
                yield "\" ";
                yield CoreExtension::getAttribute($this->env, $this->source, $context["row"], "getAttributeString", ["data-drag-id,x-show,x-transition,x-cloak"], "method", false, false, false, 46);
                yield ">
    
            ";
                // line 48
                $context['_parent'] = $context;
                $context['_seq'] = CoreExtension::ensureTraversable(CoreExtension::getAttribute($this->env, $this->source, $context["row"], "getElements", [], "any", false, false, false, 48));
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
                foreach ($context['_seq'] as $context["_key"] => $context["element"]) {
                    // line 49
                    yield "
                ";
                    // line 50
                    if (CoreExtension::getAttribute($this->env, $this->source, $context["element"], "isInstanceOf", ["Gibbon\\Forms\\Layout\\Label"], "method", false, false, false, 50)) {
                        // line 51
                        yield "                    ";
                        $context["class"] = "sm:w-2/5 flex flex-col justify-center sm:mb-0 text-xs ";
                        // line 52
                        yield "                ";
                    } elseif (CoreExtension::getAttribute($this->env, $this->source, $context["loop"], "last", [], "any", false, false, false, 52)) {
                        // line 53
                        yield "                    ";
                        $context["class"] = "flex-1 relative flex justify-end items-center";
                        // line 54
                        yield "                ";
                    } else {
                        // line 55
                        yield "                    ";
                        $context["class"] = "";
                        // line 56
                        yield "                ";
                    }
                    // line 57
                    yield "
                ";
                    // line 58
                    $context["class"] = ((($context["class"] ?? null) . " ") . CoreExtension::getAttribute($this->env, $this->source, $context["element"], "getAttribute", ["class"], "method", false, false, false, 58));
                    // line 59
                    yield "
                <div class=\"";
                    // line 60
                    yield $this->env->getRuntime('Twig\Runtime\EscaperRuntime')->escape(($context["class"] ?? null), "html", null, true);
                    yield "\" >
                    ";
                    // line 61
                    yield CoreExtension::getAttribute($this->env, $this->source, $context["element"], "getOutput", [], "any", false, false, false, 61);
                    yield "
                </div>
            ";
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
                unset($context['_seq'], $context['_iterated'], $context['_key'], $context['element'], $context['_parent'], $context['loop']);
                $context = array_intersect_key($context, $_parent) + $_parent;
                // line 64
                yield "
            </div>
        ";
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
            unset($context['_seq'], $context['_iterated'], $context['num'], $context['row'], $context['_parent'], $context['loop']);
            $context = array_intersect_key($context, $_parent) + $_parent;
            // line 67
            yield "
    </section>
    ";
        }
        // line 70
        yield "
    <script type=\"text/javascript\">
        ";
        // line 72
        $context['_parent'] = $context;
        $context['_seq'] = CoreExtension::ensureTraversable(($context["javascript"] ?? null));
        foreach ($context['_seq'] as $context["_key"] => $context["code"]) {
            // line 73
            yield "            ";
            yield $context["code"];
            yield "
        ";
        }
        $_parent = $context['_parent'];
        unset($context['_seq'], $context['_iterated'], $context['_key'], $context['code'], $context['_parent'], $context['loop']);
        $context = array_intersect_key($context, $_parent) + $_parent;
        // line 75
        yield "    </script>

</form>
";
        return; yield '';
    }

    // line 21
    public function block_header($context, array $blocks = [])
    {
        $macros = $this->macros;
        // line 22
        yield "        <header class=\"relative flex justify-between mb-2 ";
        yield ((($context["standardLayout"] ?? null)) ? ("") : (""));
        yield "\">
            ";
        // line 23
        if (CoreExtension::getAttribute($this->env, $this->source, ($context["form"] ?? null), "getHeader", [], "any", false, false, false, 23)) {
            // line 24
            yield "                <h2>";
            yield $this->env->getRuntime('Twig\Runtime\EscaperRuntime')->escape(CoreExtension::getAttribute($this->env, $this->source, ($context["form"] ?? null), "getTitle", [], "any", false, false, false, 24), "html", null, true);
            yield "</h2>
                <div class=\"linkTop flex justify-end gap-2\">
                    ";
            // line 26
            $context['_parent'] = $context;
            $context['_seq'] = CoreExtension::ensureTraversable(CoreExtension::getAttribute($this->env, $this->source, ($context["form"] ?? null), "getHeader", [], "any", false, false, false, 26));
            foreach ($context['_seq'] as $context["_key"] => $context["action"]) {
                // line 27
                yield "                        ";
                yield CoreExtension::getAttribute($this->env, $this->source, $context["action"], "getOutput", [], "any", false, false, false, 27);
                yield "
                    ";
            }
            $_parent = $context['_parent'];
            unset($context['_seq'], $context['_iterated'], $context['_key'], $context['action'], $context['_parent'], $context['loop']);
            $context = array_intersect_key($context, $_parent) + $_parent;
            // line 29
            yield "                </div>
            ";
        }
        // line 31
        yield "        </header>
    ";
        return; yield '';
    }

    /**
     * @codeCoverageIgnore
     */
    public function getTemplateName()
    {
        return "components/formSearch.twig.html";
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
        return array (  288 => 31,  284 => 29,  275 => 27,  271 => 26,  265 => 24,  263 => 23,  258 => 22,  254 => 21,  246 => 75,  237 => 73,  233 => 72,  229 => 70,  224 => 67,  208 => 64,  191 => 61,  187 => 60,  184 => 59,  182 => 58,  179 => 57,  176 => 56,  173 => 55,  170 => 54,  167 => 53,  164 => 52,  161 => 51,  159 => 50,  156 => 49,  139 => 48,  128 => 46,  125 => 45,  122 => 44,  120 => 43,  117 => 42,  100 => 41,  96 => 39,  94 => 38,  91 => 37,  80 => 35,  76 => 34,  73 => 33,  71 => 21,  68 => 20,  62 => 18,  60 => 17,  57 => 16,  51 => 14,  49 => 13,  42 => 11,  39 => 10,);
    }

    public function getSourceContext()
    {
        return new Source("", "components/formSearch.twig.html", "/var/www/html/gibbon/resources/templates/components/formSearch.twig.html");
    }
}
