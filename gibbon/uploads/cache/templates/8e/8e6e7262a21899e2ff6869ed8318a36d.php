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

/* ui/timetableDay.twig.html */
class __TwigTemplate_acdaa8bf1fb8a18803556906480ccbd5 extends Template
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
        $context["isToday"] = (CoreExtension::getAttribute($this->env, $this->source, CoreExtension::getAttribute($this->env, $this->source, ($context["structure"] ?? null), "getToday", [], "any", false, false, false, 11), "format", ["Y-m-d"], "method", false, false, false, 11) == CoreExtension::getAttribute($this->env, $this->source, ($context["day"] ?? null), "date", [], "any", false, false, false, 11));
        // line 12
        $context["specialDay"] = CoreExtension::getAttribute($this->env, $this->source, ($context["structure"] ?? null), "getSpecialDay", [CoreExtension::getAttribute($this->env, $this->source, ($context["day"] ?? null), "date", [], "any", false, false, false, 12)], "method", false, false, false, 12);
        // line 13
        $context["indexDepth"] = ["", "pl-2", "pl-4", "pl-6", "pl-8"];
        // line 14
        yield "
<div class=\"relative flex-1 flex-col\" :class=\"{'flex': ttActiveDay == '";
        // line 15
        yield $this->env->getRuntime('Twig\Runtime\EscaperRuntime')->escape(CoreExtension::getAttribute($this->env, $this->source, ($context["day"] ?? null), "nameShort", [], "any", false, false, false, 15), "html", null, true);
        yield "', 'hidden md:flex': ttActiveDay != '";
        yield $this->env->getRuntime('Twig\Runtime\EscaperRuntime')->escape(CoreExtension::getAttribute($this->env, $this->source, ($context["day"] ?? null), "nameShort", [], "any", false, false, false, 15), "html", null, true);
        yield "'}\">

    ";
        // line 17
        $context["column"] = CoreExtension::getAttribute($this->env, $this->source, ($context["structure"] ?? null), "getColumn", [CoreExtension::getAttribute($this->env, $this->source, ($context["day"] ?? null), "date", [], "any", false, false, false, 17)], "method", false, false, false, 17);
        // line 18
        yield "
    ";
        // line 19
        if ((CoreExtension::getAttribute($this->env, $this->source, ($context["specialDay"] ?? null), "type", [], "any", false, false, false, 19) == "School Closure")) {
            // line 20
            yield "    <div class=\"flex-1 flex flex-col items-center justify-center gap-1 rounded-b border border-red-800/50 bg-red-200\" style=\"height: ";
            yield $this->env->getRuntime('Twig\Runtime\EscaperRuntime')->escape(CoreExtension::getAttribute($this->env, $this->source, ($context["structure"] ?? null), "minutesToPixels", [CoreExtension::getAttribute($this->env, $this->source, ($context["structure"] ?? null), "timeDifference", [CoreExtension::getAttribute($this->env, $this->source, ($context["structure"] ?? null), "getStartTime", [], "any", false, false, false, 20), CoreExtension::getAttribute($this->env, $this->source, ($context["structure"] ?? null), "getEndTime", [], "any", false, false, false, 20)], "method", false, false, false, 20)], "method", false, false, false, 20), "html", null, true);
            yield "px\">
        <span class=\"text-sm text-red-800\">
            ";
            // line 22
            yield $this->env->getRuntime('Twig\Runtime\EscaperRuntime')->escape($this->env->getFunction('__')->getCallable()("School Closed"), "html", null, true);
            yield "
        </span>
        <span class=\"text-xs text-red-800\">
            ";
            // line 25
            yield $this->env->getRuntime('Twig\Runtime\EscaperRuntime')->escape(CoreExtension::getAttribute($this->env, $this->source, ($context["specialDay"] ?? null), "name", [], "any", false, false, false, 25), "html", null, true);
            yield "
        </span>
    </div>
    ";
        } else {
            // line 29
            yield "    
    <div class=\" flex flex-col rounded-b border-b border-r border-l ";
            // line 30
            yield (( !($context["column"] ?? null)) ? ("flex-1") : (""));
            yield " ";
            yield ((($context["isToday"] ?? null)) ? ("bg-yellow-50") : (""));
            yield " \">
        
        ";
            // line 32
            $context["lastTimeEnd"] = CoreExtension::getAttribute($this->env, $this->source, ($context["structure"] ?? null), "getStartTime", [], "any", false, false, false, 32);
            // line 33
            yield "        ";
            $context['_parent'] = $context;
            $context['_seq'] = CoreExtension::ensureTraversable(($context["column"] ?? null));
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
            foreach ($context['_seq'] as $context["_key"] => $context["item"]) {
                // line 34
                yield "            ";
                $context["firstItem"] = CoreExtension::getAttribute($this->env, $this->source, $context["loop"], "first", [], "any", false, false, false, 34);
                // line 35
                yield "
            ";
                // line 36
                if (( !Twig\Extension\CoreExtension::testEmpty(($context["lastTimeEnd"] ?? null)) && (CoreExtension::getAttribute($this->env, $this->source, $context["item"], "timeStart", [], "any", false, false, false, 36) != ($context["lastTimeEnd"] ?? null)))) {
                    // line 37
                    yield "            <div class=\"flex border-t opacity-50 ";
                    yield (( !CoreExtension::getAttribute($this->env, $this->source, $context["loop"], "first", [], "any", false, false, false, 37)) ? ("border-dashed") : (""));
                    yield "\" style=\"height: ";
                    yield $this->env->getRuntime('Twig\Runtime\EscaperRuntime')->escape(CoreExtension::getAttribute($this->env, $this->source, ($context["structure"] ?? null), "minutesToPixels", [CoreExtension::getAttribute($this->env, $this->source, ($context["structure"] ?? null), "timeDifference", [($context["lastTimeEnd"] ?? null), CoreExtension::getAttribute($this->env, $this->source, $context["item"], "timeStart", [], "any", false, false, false, 37)], "method", false, false, false, 37)], "method", false, false, false, 37), "html", null, true);
                    yield "px\">
            </div>
            ";
                    // line 39
                    $context["firstItem"] = false;
                    // line 40
                    yield "            ";
                }
                // line 41
                yield "
            <div class=\"relative group flex items-start justify-start border-t border-gray-300 transition duration-300 ";
                // line 42
                yield (( !($context["firstItem"] ?? null)) ? ("border-dashed") : (""));
                yield "\" style=\"height: ";
                yield $this->env->getRuntime('Twig\Runtime\EscaperRuntime')->escape(CoreExtension::getAttribute($this->env, $this->source, ($context["structure"] ?? null), "minutesToPixels", [CoreExtension::getAttribute($this->env, $this->source, $context["item"], "duration", [], "any", false, false, false, 42)], "method", false, false, false, 42), "html", null, true);
                yield "px\">

                <div class=\"flex w-full items-start justify-between border-gray-500 px-1.5 py-1\">
                    <span class=\"text-xxs text-gray-500 \">";
                // line 45
                (((CoreExtension::getAttribute($this->env, $this->source, $context["item"], "duration", [], "any", false, false, false, 45) >= 15)) ? (yield $this->env->getRuntime('Twig\Runtime\EscaperRuntime')->escape(CoreExtension::getAttribute($this->env, $this->source, $context["item"], "title", [], "any", false, false, false, 45), "html", null, true)) : (yield ""));
                yield "</span>
                    <span class=\"inline md:hidden lg:inline text-xxs text-gray-500 opacity-0 group-hover:opacity-100 transition duration-300\">";
                // line 46
                yield $this->env->getRuntime('Twig\Runtime\EscaperRuntime')->escape(Twig\Extension\CoreExtension::trim(Twig\Extension\CoreExtension::slice($this->env->getCharset(), CoreExtension::getAttribute($this->env, $this->source, $context["item"], "timeStart", [], "any", false, false, false, 46), 0, 5), "0", "left"), "html", null, true);
                yield " - ";
                yield $this->env->getRuntime('Twig\Runtime\EscaperRuntime')->escape(Twig\Extension\CoreExtension::trim(Twig\Extension\CoreExtension::slice($this->env->getCharset(), CoreExtension::getAttribute($this->env, $this->source, $context["item"], "timeEnd", [], "any", false, false, false, 46), 0, 5), "0", "left"), "html", null, true);
                yield "</span>
                </div>

                ";
                // line 49
                $context["lastTimeEnd"] = CoreExtension::getAttribute($this->env, $this->source, $context["item"], "timeEnd", [], "any", false, false, false, 49);
                // line 50
                yield "
                ";
                // line 51
                if (((CoreExtension::getAttribute($this->env, $this->source, $context["item"], "date", [], "any", false, false, false, 51) > CoreExtension::getAttribute($this->env, $this->source, CoreExtension::getAttribute($this->env, $this->source, ($context["structure"] ?? null), "getToday", [], "any", false, false, false, 51), "format", ["Y-m-d"], "method", false, false, false, 51)) || (($context["isToday"] ?? null) && (CoreExtension::getAttribute($this->env, $this->source, $context["item"], "timeStart", [], "any", false, false, false, 51) >= ($context["now"] ?? null))))) {
                    // line 52
                    yield "                    ";
                    if (((((($context["gibbonSpaceID"] ?? null) && CoreExtension::getAttribute($this->env, $this->source, (($__internal_compile_0 = ($context["layers"] ?? null)) && is_array($__internal_compile_0) || $__internal_compile_0 instanceof ArrayAccess ? ($__internal_compile_0["Bookings"] ?? null) : null), "isActive", [], "any", false, false, false, 52)) && CoreExtension::getAttribute($this->env, $this->source, (($__internal_compile_1 = ($context["layers"] ?? null)) && is_array($__internal_compile_1) || $__internal_compile_1 instanceof ArrayAccess ? ($__internal_compile_1["Bookings"] ?? null) : null), "isBookable", [], "any", false, false, false, 52)) &&  !CoreExtension::getAttribute($this->env, $this->source, $context["item"], "hasStatus", ["overlap"], "method", false, false, false, 52)) && (($context["format"] ?? null) != "print"))) {
                        // line 53
                        yield "                        ";
                        $context["iconSize"] = (((CoreExtension::getAttribute($this->env, $this->source, $context["item"], "duration", [], "any", false, false, false, 53) >= 50)) ? ("size-6") : ((((CoreExtension::getAttribute($this->env, $this->source, $context["item"], "duration", [], "any", false, false, false, 53) >= 30)) ? ("size-5") : ("size-3"))));
                        // line 54
                        yield "                        <a x-show=\"";
                        yield $this->env->getRuntime('Twig\Runtime\EscaperRuntime')->escape(CoreExtension::getAttribute($this->env, $this->source, (($__internal_compile_2 = ($context["layers"] ?? null)) && is_array($__internal_compile_2) || $__internal_compile_2 instanceof ArrayAccess ? ($__internal_compile_2["Bookings"] ?? null) : null), "getID", [], "any", false, false, false, 54), "html", null, true);
                        yield "\" href=\"";
                        yield $this->env->getRuntime('Twig\Runtime\EscaperRuntime')->escape(($context["absoluteURL"] ?? null), "html", null, true);
                        yield "/index.php?q=/modules/Timetable/spaceBooking_manage_add.php&gibbonSpaceID=";
                        yield $this->env->getRuntime('Twig\Runtime\EscaperRuntime')->escape(($context["gibbonSpaceID"] ?? null), "html", null, true);
                        yield "&date=";
                        yield $this->env->getRuntime('Twig\Runtime\EscaperRuntime')->escape(CoreExtension::getAttribute($this->env, $this->source, $context["item"], "date", [], "any", false, false, false, 54), "html", null, true);
                        yield "&timeStart=";
                        yield $this->env->getRuntime('Twig\Runtime\EscaperRuntime')->escape(CoreExtension::getAttribute($this->env, $this->source, $context["item"], "timeStart", [], "any", false, false, false, 54), "html", null, true);
                        yield "&timeEnd=";
                        yield $this->env->getRuntime('Twig\Runtime\EscaperRuntime')->escape(CoreExtension::getAttribute($this->env, $this->source, $context["item"], "timeEnd", [], "any", false, false, false, 54), "html", null, true);
                        yield "&source=tt\" class=\"absolute bottom-0 right-0 mr-1 cursor-pointer pointer-events-auto\" title=\"";
                        yield $this->env->getRuntime('Twig\Runtime\EscaperRuntime')->escape($this->env->getFunction('__')->getCallable()("Add Facility Booking"), "html", null, true);
                        yield "\">
                            ";
                        // line 55
                        yield $this->env->getFunction('icon')->getCallable()("solid", "add", (($context["iconSize"] ?? null) . " text-gray-600 hover:text-gray-800"));
                        yield "
                        </a>
                    ";
                    }
                    // line 58
                    yield "                ";
                }
                // line 59
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
            unset($context['_seq'], $context['_iterated'], $context['_key'], $context['item'], $context['_parent'], $context['loop']);
            $context = array_intersect_key($context, $_parent) + $_parent;
            // line 63
            yield "    </div>

    ";
        }
        // line 66
        yield "
    ";
        // line 67
        $context['_parent'] = $context;
        $context['_seq'] = CoreExtension::ensureTraversable(($context["layers"] ?? null));
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
        foreach ($context['_seq'] as $context["_key"] => $context["layer"]) {
            // line 68
            yield "        ";
            if (CoreExtension::getAttribute($this->env, $this->source, ($context["structure"] ?? null), "isLayerVisible", [CoreExtension::getAttribute($this->env, $this->source, $context["layer"], "getType", [], "any", false, false, false, 68), CoreExtension::getAttribute($this->env, $this->source, ($context["day"] ?? null), "date", [], "any", false, false, false, 68)], "method", false, false, false, 68)) {
                // line 69
                yield "            ";
                $context['_parent'] = $context;
                $context['_seq'] = CoreExtension::ensureTraversable(CoreExtension::getAttribute($this->env, $this->source, $context["layer"], "getItemsByDate", [CoreExtension::getAttribute($this->env, $this->source, ($context["day"] ?? null), "date", [], "any", false, false, false, 69)], "method", false, false, false, 69));
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
                foreach ($context['_seq'] as $context["_key"] => $context["item"]) {
                    // line 70
                    yield "                <div class=\"absolute left-0 w-full px-px ";
                    yield $this->env->getRuntime('Twig\Runtime\EscaperRuntime')->escape(CoreExtension::getAttribute($this->env, $this->source, ($context["indexDepth"] ?? null), CoreExtension::getAttribute($this->env, $this->source, $context["item"], "index", [], "any", false, false, false, 70), [], "any", false, false, false, 70), "html", null, true);
                    yield "\" style=\"top: ";
                    yield $this->env->getRuntime('Twig\Runtime\EscaperRuntime')->escape((CoreExtension::getAttribute($this->env, $this->source, ($context["structure"] ?? null), "timeToPixels", [CoreExtension::getAttribute($this->env, $this->source, $context["item"], "timeStart", [], "any", false, false, false, 70)], "method", false, false, false, 70) + 1), "html", null, true);
                    yield "px\">
                ";
                    // line 71
                    yield Twig\Extension\CoreExtension::include($this->env, $context, "ui/timetableItem.twig.html");
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
                unset($context['_seq'], $context['_iterated'], $context['_key'], $context['item'], $context['_parent'], $context['loop']);
                $context = array_intersect_key($context, $_parent) + $_parent;
                // line 74
                yield "        ";
            }
            // line 75
            yield "    ";
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
        unset($context['_seq'], $context['_iterated'], $context['_key'], $context['layer'], $context['_parent'], $context['loop']);
        $context = array_intersect_key($context, $_parent) + $_parent;
        // line 76
        yield "
</div>
";
        return; yield '';
    }

    /**
     * @codeCoverageIgnore
     */
    public function getTemplateName()
    {
        return "ui/timetableDay.twig.html";
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
        return array (  300 => 76,  286 => 75,  283 => 74,  266 => 71,  259 => 70,  241 => 69,  238 => 68,  221 => 67,  218 => 66,  213 => 63,  196 => 59,  193 => 58,  187 => 55,  170 => 54,  167 => 53,  164 => 52,  162 => 51,  159 => 50,  157 => 49,  149 => 46,  145 => 45,  137 => 42,  134 => 41,  131 => 40,  129 => 39,  121 => 37,  119 => 36,  116 => 35,  113 => 34,  95 => 33,  93 => 32,  86 => 30,  83 => 29,  76 => 25,  70 => 22,  64 => 20,  62 => 19,  59 => 18,  57 => 17,  50 => 15,  47 => 14,  45 => 13,  43 => 12,  41 => 11,  38 => 10,);
    }

    public function getSourceContext()
    {
        return new Source("", "ui/timetableDay.twig.html", "/var/www/html/gibbon/resources/templates/ui/timetableDay.twig.html");
    }
}
