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

/* ui/timetableItem.twig.html */
class __TwigTemplate_f6e19e19d19c9988ec0f5607d2fc1ce8 extends Template
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
        $context["isActive"] = (((($context["isToday"] ?? null) && (($context["now"] ?? null) > CoreExtension::getAttribute($this->env, $this->source, ($context["item"] ?? null), "timeStart", [], "any", false, false, false, 11))) && (($context["now"] ?? null) <= CoreExtension::getAttribute($this->env, $this->source, ($context["item"] ?? null), "timeEnd", [], "any", false, false, false, 11))) && ( !($context["format"] ?? null) == "print"));
        // line 12
        $context["color"] = CoreExtension::getAttribute($this->env, $this->source, ($context["structure"] ?? null), "getColors", [(((CoreExtension::getAttribute($this->env, $this->source, ($context["item"] ?? null), "color", [], "any", true, true, false, 12) &&  !(null === CoreExtension::getAttribute($this->env, $this->source, ($context["item"] ?? null), "color", [], "any", false, false, false, 12)))) ? (CoreExtension::getAttribute($this->env, $this->source, ($context["item"] ?? null), "color", [], "any", false, false, false, 12)) : (CoreExtension::getAttribute($this->env, $this->source, ($context["layer"] ?? null), "getColor", [], "any", false, false, false, 12)))], "method", false, false, false, 12);
        // line 13
        $context["duration"] = ((CoreExtension::getAttribute($this->env, $this->source, ($context["item"] ?? null), "allDay", [], "any", false, false, false, 13)) ? (30) : (CoreExtension::getAttribute($this->env, $this->source, ($context["structure"] ?? null), "timeDifference", [CoreExtension::getAttribute($this->env, $this->source, ($context["item"] ?? null), "timeStart", [], "any", false, false, false, 13), CoreExtension::getAttribute($this->env, $this->source, ($context["item"] ?? null), "timeEnd", [], "any", false, false, false, 13)], "method", false, false, false, 13)));
        // line 14
        yield "
<div x-data=\"{showOverlap: false}\" class=\"";
        // line 15
        yield ((( !CoreExtension::getAttribute($this->env, $this->source, ($context["item"] ?? null), "allDay", [], "any", false, false, false, 15) &&  !($context["overlap"] ?? null))) ? ("ttItem") : (""));
        yield " flex flex-col w-full rounded outline hover:ring ";
        yield $this->env->getRuntime('Twig\Runtime\EscaperRuntime')->escape(((($context["isActive"] ?? null)) ? (("relative outline-3 " . CoreExtension::getAttribute($this->env, $this->source, ($context["color"] ?? null), "outline", [], "any", false, false, false, 15))) : (("outline-1 " . CoreExtension::getAttribute($this->env, $this->source, ($context["color"] ?? null), "outlineLight", [], "any", false, false, false, 15)))), "html", null, true);
        yield " ";
        yield $this->env->getRuntime('Twig\Runtime\EscaperRuntime')->escape(CoreExtension::getAttribute($this->env, $this->source, ($context["color"] ?? null), "outlineHover", [], "any", false, false, false, 15), "html", null, true);
        yield " ";
        yield $this->env->getRuntime('Twig\Runtime\EscaperRuntime')->escape(CoreExtension::getAttribute($this->env, $this->source, ($context["color"] ?? null), "background", [], "any", false, false, false, 15), "html", null, true);
        yield " ";
        yield (((CoreExtension::getAttribute($this->env, $this->source, ($context["item"] ?? null), "style", [], "any", false, false, false, 15) == "stripe")) ? ("bg-stripe-overlay") : (""));
        yield " ";
        yield ((((($context["format"] ?? null) == "print") &&  !CoreExtension::getAttribute($this->env, $this->source, ($context["layer"] ?? null), "isActive", [], "any", false, false, false, 15))) ? ("hidden") : (""));
        yield "\" style=\"height: ";
        yield $this->env->getRuntime('Twig\Runtime\EscaperRuntime')->escape((CoreExtension::getAttribute($this->env, $this->source, ($context["structure"] ?? null), "minutesToPixels", [($context["duration"] ?? null)], "method", false, false, false, 15) - 1), "html", null, true);
        yield "px; ";
        yield $this->env->getRuntime('Twig\Runtime\EscaperRuntime')->escape(CoreExtension::getAttribute($this->env, $this->source, ($context["color"] ?? null), "style", [], "any", false, false, false, 15), "html", null, true);
        yield "\" :class=\"{'hidden': !";
        yield $this->env->getRuntime('Twig\Runtime\EscaperRuntime')->escape(CoreExtension::getAttribute($this->env, $this->source, ($context["layer"] ?? null), "getID", [], "any", false, false, false, 15), "html", null, true);
        yield " 
 ";
        // line 16
        if ( !CoreExtension::getAttribute($this->env, $this->source, ($context["item"] ?? null), "hasStatus", ["myEvent"], "method", false, false, false, 16)) {
            yield " || (";
            yield $this->env->getRuntime('Twig\Runtime\EscaperRuntime')->escape(CoreExtension::getAttribute($this->env, $this->source, ($context["layer"] ?? null), "getID", [], "any", false, false, false, 16), "html", null, true);
            yield " && onlyMyEvents && '";
            yield $this->env->getRuntime('Twig\Runtime\EscaperRuntime')->escape(CoreExtension::getAttribute($this->env, $this->source, ($context["layer"] ?? null), "type", [], "any", false, false, false, 16), "html", null, true);
            yield "' == 'calendar') ";
        }
        yield " 
}\" ";
        // line 17
        yield (( !CoreExtension::getAttribute($this->env, $this->source, ($context["layer"] ?? null), "isActive", [], "any", false, false, false, 17)) ? (" x-cloak ") : (""));
        yield " 

x-tooltip.white=\"
    <div class='w-72 flex flex-col py-2 gap-1 overflow-hidden'>
        <div class='px-2 pb-1'>
            <div class='flex justify-between leading-normal'>
                <span class='font-semibold text-sm'>";
        // line 23
        yield $this->env->getRuntime('Twig\Runtime\EscaperRuntime')->escape((((CoreExtension::getAttribute($this->env, $this->source, ($context["item"] ?? null), "label", [], "any", true, true, false, 23) &&  !(null === CoreExtension::getAttribute($this->env, $this->source, ($context["item"] ?? null), "label", [], "any", false, false, false, 23)))) ? (CoreExtension::getAttribute($this->env, $this->source, ($context["item"] ?? null), "label", [], "any", false, false, false, 23)) : (CoreExtension::getAttribute($this->env, $this->source, ($context["item"] ?? null), "title", [], "any", false, false, false, 23))), "html", null, true);
        yield "</span>
                <span class='tag ml-2 text-xxs h-5 border-0 outline outline-1 ";
        // line 24
        yield $this->env->getRuntime('Twig\Runtime\EscaperRuntime')->escape(CoreExtension::getAttribute($this->env, $this->source, ($context["color"] ?? null), "outlineLight", [], "any", false, false, false, 24), "html", null, true);
        yield " ";
        yield $this->env->getRuntime('Twig\Runtime\EscaperRuntime')->escape(CoreExtension::getAttribute($this->env, $this->source, ($context["color"] ?? null), "text", [], "any", false, false, false, 24), "html", null, true);
        yield " ";
        yield $this->env->getRuntime('Twig\Runtime\EscaperRuntime')->escape(CoreExtension::getAttribute($this->env, $this->source, ($context["color"] ?? null), "background", [], "any", false, false, false, 24), "html", null, true);
        yield "' style='";
        yield $this->env->getRuntime('Twig\Runtime\EscaperRuntime')->escape(CoreExtension::getAttribute($this->env, $this->source, ($context["color"] ?? null), "style", [], "any", false, false, false, 24), "html", null, true);
        yield " ";
        yield $this->env->getRuntime('Twig\Runtime\EscaperRuntime')->escape(CoreExtension::getAttribute($this->env, $this->source, ($context["color"] ?? null), "textStyle", [], "any", false, false, false, 24), "html", null, true);
        yield "'>";
        yield $this->env->getRuntime('Twig\Runtime\EscaperRuntime')->escape(CoreExtension::getAttribute($this->env, $this->source, ($context["item"] ?? null), "type", [], "any", false, false, false, 24), "html", null, true);
        yield "</span>
            </div>
            <div class='font-normal mt-1'>";
        // line 26
        yield $this->env->getRuntime('Twig\Runtime\EscaperRuntime')->escape((((CoreExtension::getAttribute($this->env, $this->source, ($context["item"] ?? null), "description", [], "any", true, true, false, 26) &&  !(null === CoreExtension::getAttribute($this->env, $this->source, ($context["item"] ?? null), "description", [], "any", false, false, false, 26)))) ? (CoreExtension::getAttribute($this->env, $this->source, ($context["item"] ?? null), "description", [], "any", false, false, false, 26)) : (CoreExtension::getAttribute($this->env, $this->source, ($context["item"] ?? null), "subtitle", [], "any", false, false, false, 26))), "html", null, true);
        yield "</div>
        </div>
        
        <div class='px-2 pt-2 border-t flex justify-between leading-relaxed'>
            <div>";
        // line 30
        yield $this->env->getRuntime('Twig\Runtime\EscaperRuntime')->escape($this->env->getFunction('icon')->getCallable()("outline", "clock", "size-4 text-gray-600 inline align-middle mr-1", ["stroke-width" => 2.4]));
        yield " ";
        yield $this->env->getRuntime('Twig\Runtime\EscaperRuntime')->escape(((CoreExtension::getAttribute($this->env, $this->source, ($context["item"] ?? null), "allDay", [], "any", false, false, false, 30)) ? ($this->env->getFunction('__')->getCallable()("All Day")) : (((Twig\Extension\CoreExtension::trim(Twig\Extension\CoreExtension::slice($this->env->getCharset(), CoreExtension::getAttribute($this->env, $this->source, ($context["item"] ?? null), "timeStart", [], "any", false, false, false, 30), 0, 5), "0", "left") . " - ") . Twig\Extension\CoreExtension::trim(Twig\Extension\CoreExtension::slice($this->env->getCharset(), CoreExtension::getAttribute($this->env, $this->source, ($context["item"] ?? null), "timeEnd", [], "any", false, false, false, 30), 0, 5), "0", "left")))), "html", null, true);
        yield "
                ";
        // line 31
        yield ((($context["isActive"] ?? null)) ? ($this->env->getRuntime('Twig\Runtime\EscaperRuntime')->escape($this->env->getFunction('formatUsing')->getCallable()("tag", $this->env->getFunction('__')->getCallable()("Active"), "success ml-2 text-xxs"))) : (""));
        yield "
                ";
        // line 32
        yield ((CoreExtension::getAttribute($this->env, $this->source, ($context["item"] ?? null), "hasStatus", ["absent"], "method", false, false, false, 32)) ? ($this->env->getRuntime('Twig\Runtime\EscaperRuntime')->escape($this->env->getFunction('formatUsing')->getCallable()("tag", $this->env->getFunction('__')->getCallable()("Absent"), "dull ml-2 text-xxs"))) : (""));
        yield "
            </div>
        </div>

        ";
        // line 36
        if ((CoreExtension::getAttribute($this->env, $this->source, ($context["item"] ?? null), "location", [], "any", false, false, false, 36) || CoreExtension::getAttribute($this->env, $this->source, ($context["item"] ?? null), "phone", [], "any", false, false, false, 36))) {
            // line 37
            yield "        <div class='px-2 flex justify-between leading-relaxed'>
            <div>
                ";
            // line 39
            yield ((CoreExtension::getAttribute($this->env, $this->source, ($context["item"] ?? null), "location", [], "any", false, false, false, 39)) ? ($this->env->getRuntime('Twig\Runtime\EscaperRuntime')->escape($this->env->getFunction('icon')->getCallable()("solid", "map-pin", "size-4 text-gray-600 inline align-middle mr-1"))) : (""));
            yield " ";
            yield $this->env->getRuntime('Twig\Runtime\EscaperRuntime')->escape(CoreExtension::getAttribute($this->env, $this->source, ($context["item"] ?? null), "location", [], "any", false, false, false, 39), "html", null, true);
            yield " 
                ";
            // line 40
            yield ((CoreExtension::getAttribute($this->env, $this->source, ($context["item"] ?? null), "hasStatus", ["spaceChanged"], "method", false, false, false, 40)) ? ($this->env->getRuntime('Twig\Runtime\EscaperRuntime')->escape($this->env->getFunction('formatUsing')->getCallable()("tag", $this->env->getFunction('__')->getCallable()("Changed"), "error ml-2 text-xxs"))) : (""));
            yield "
            </div>
            <div>";
            // line 42
            yield ((CoreExtension::getAttribute($this->env, $this->source, ($context["item"] ?? null), "phone", [], "any", false, false, false, 42)) ? ($this->env->getRuntime('Twig\Runtime\EscaperRuntime')->escape($this->env->getFunction('icon')->getCallable()("solid", "phone", "size-4 text-gray-600 inline align-middle mr-1"))) : (""));
            yield " ";
            yield $this->env->getRuntime('Twig\Runtime\EscaperRuntime')->escape(CoreExtension::getAttribute($this->env, $this->source, ($context["item"] ?? null), "phone", [], "any", false, false, false, 42), "html", null, true);
            yield "</div>
        </div>
        ";
        }
        // line 45
        yield "    </div>
\">

    ";
        // line 48
        if ((($context["duration"] ?? null) >= 40)) {
            // line 49
            yield "    <div class=\"flex items-start justify-between  border-gray-500 py-1 px-1.5\">
        <span class=\"text-xxs text-gray-700 \">";
            // line 50
            yield $this->env->getRuntime('Twig\Runtime\EscaperRuntime')->escape((((CoreExtension::getAttribute($this->env, $this->source, ($context["item"] ?? null), "period", [], "any", true, true, false, 50) &&  !(null === CoreExtension::getAttribute($this->env, $this->source, ($context["item"] ?? null), "period", [], "any", false, false, false, 50)))) ? (CoreExtension::getAttribute($this->env, $this->source, ($context["item"] ?? null), "period", [], "any", false, false, false, 50)) : (CoreExtension::getAttribute($this->env, $this->source, ($context["item"] ?? null), "type", [], "any", false, false, false, 50))), "html", null, true);
            yield "</span>
        ";
            // line 51
            if (Twig\Extension\CoreExtension::testEmpty(CoreExtension::getAttribute($this->env, $this->source, ($context["item"] ?? null), "overlap", [], "any", false, false, false, 51))) {
                // line 52
                yield "        <span class=\"inline md:hidden lg:inline text-xxs text-gray-700 \">";
                yield $this->env->getRuntime('Twig\Runtime\EscaperRuntime')->escape(Twig\Extension\CoreExtension::trim(Twig\Extension\CoreExtension::slice($this->env->getCharset(), CoreExtension::getAttribute($this->env, $this->source, ($context["item"] ?? null), "timeStart", [], "any", false, false, false, 52), 0, 5), "0", "left"), "html", null, true);
                yield " - ";
                yield $this->env->getRuntime('Twig\Runtime\EscaperRuntime')->escape(Twig\Extension\CoreExtension::trim(Twig\Extension\CoreExtension::slice($this->env->getCharset(), CoreExtension::getAttribute($this->env, $this->source, ($context["item"] ?? null), "timeEnd", [], "any", false, false, false, 52), 0, 5), "0", "left"), "html", null, true);
                yield "</span>
        ";
            }
            // line 54
            yield "    </div>
    ";
        }
        // line 56
        yield "
    
    <a href=\"";
        // line 58
        yield $this->env->getRuntime('Twig\Runtime\EscaperRuntime')->escape(CoreExtension::getAttribute($this->env, $this->source, ($context["item"] ?? null), "link", [], "any", false, false, false, 58), "html", null, true);
        yield "\" class=\"flex flex-col items-center cursor-pointer h-full px-2 ";
        yield (((($context["duration"] ?? null) >= 40)) ? ("justify-start") : ("justify-center"));
        yield " ";
        yield $this->env->getRuntime('Twig\Runtime\EscaperRuntime')->escape(CoreExtension::getAttribute($this->env, $this->source, ($context["color"] ?? null), "text", [], "any", false, false, false, 58), "html", null, true);
        yield " ";
        yield $this->env->getRuntime('Twig\Runtime\EscaperRuntime')->escape(CoreExtension::getAttribute($this->env, $this->source, ($context["color"] ?? null), "textHover", [], "any", false, false, false, 58), "html", null, true);
        yield " overflow-y-hidden\">
        ";
        // line 59
        if ((($context["duration"] ?? null) >= 15)) {
            // line 60
            yield "        <div class=\"inline-block font-bold ";
            yield ((((($context["duration"] ?? null) > 30) && (Twig\Extension\CoreExtension::length($this->env->getCharset(), CoreExtension::getAttribute($this->env, $this->source, ($context["item"] ?? null), "title", [], "any", false, false, false, 60)) <= 20))) ? ("text-sm") : ("text-xs mt-1"));
            yield "\" style=\"";
            yield $this->env->getRuntime('Twig\Runtime\EscaperRuntime')->escape(CoreExtension::getAttribute($this->env, $this->source, ($context["color"] ?? null), "textStyle", [], "any", false, false, false, 60), "html", null, true);
            yield "\">
            ";
            // line 61
            yield $this->env->getRuntime('Twig\Runtime\EscaperRuntime')->escape((((($context["duration"] ?? null) >= 40)) ? (Twig\Extension\CoreExtension::slice($this->env->getCharset(), CoreExtension::getAttribute($this->env, $this->source, ($context["item"] ?? null), "title", [], "any", false, false, false, 61), 0, 40)) : (Twig\Extension\CoreExtension::slice($this->env->getCharset(), CoreExtension::getAttribute($this->env, $this->source, ($context["item"] ?? null), "title", [], "any", false, false, false, 61), 0, 22))), "html", null, true);
            yield "
        </div>
        ";
        }
        // line 64
        yield "
        ";
        // line 65
        if (((($context["duration"] ?? null) >= 40) && CoreExtension::getAttribute($this->env, $this->source, ($context["item"] ?? null), "subtitle", [], "any", false, false, false, 65))) {
            // line 66
            yield "        <span class=\"inline-block text-xxs rounded ";
            yield ((CoreExtension::getAttribute($this->env, $this->source, ($context["item"] ?? null), "hasStatus", ["spaceChanged"], "method", false, false, false, 66)) ? ("border border-red-700 text-red-700 px-1") : ("text-gray-700"));
            yield "\">
            ";
            // line 67
            if (CoreExtension::getAttribute($this->env, $this->source, ($context["item"] ?? null), "hasStatus", ["spaceChanged"], "method", false, false, false, 67)) {
                // line 68
                yield "                ";
                yield $this->env->getFunction('icon')->getCallable()("basic", "arrow-move", "size-3 text-red-700 inline align-sub");
                yield "
            ";
            }
            // line 70
            yield "
            ";
            // line 71
            yield $this->env->getRuntime('Twig\Runtime\EscaperRuntime')->escape(Twig\Extension\CoreExtension::slice($this->env->getCharset(), CoreExtension::getAttribute($this->env, $this->source, ($context["item"] ?? null), "subtitle", [], "any", false, false, false, 71), 0, 30), "html", null, true);
            yield "
        </span>
        ";
        }
        // line 74
        yield "    </a>
    
    ";
        // line 76
        if ((CoreExtension::getAttribute($this->env, $this->source, ($context["item"] ?? null), "overlap", [], "any", false, false, false, 76) && (Twig\Extension\CoreExtension::length($this->env->getCharset(), CoreExtension::getAttribute($this->env, $this->source, ($context["item"] ?? null), "overlap", [], "any", false, false, false, 76)) > 0))) {
            // line 77
            yield "    <button class=\"block rounded absolute top-0 right-0 mt-1 mr-1 p-0.5 text-xxs leading-none bg-transparent hover:bg-gray-500/50 text-red-700 hover:text-red-800\" @click=\"showOverlap = true\">
        <span class=\"h-3 font-semibold\">
            +";
            // line 79
            yield $this->env->getRuntime('Twig\Runtime\EscaperRuntime')->escape(Twig\Extension\CoreExtension::length($this->env->getCharset(), CoreExtension::getAttribute($this->env, $this->source, ($context["item"] ?? null), "overlap", [], "any", false, false, false, 79)), "html", null, true);
            yield "
        </span>

        ";
            // line 82
            yield $this->env->getFunction('icon')->getCallable()("outline", "squares", "size-4 inline align-middle", ["stroke-width" => 2]);
            yield "
    </button>

    <div x-cloak x-transition x-show=\"showOverlap\" @click.outside=\"showOverlap = false\" class=\"absolute p-2 -ml-2 mt-8 z-20 flex flex-col gap-2 items-start justify-end rounded outline outline-1 outline-gray-400 bg-white shadow-lg\" style=\"width: calc(100% + 1rem)\">
        ";
            // line 86
            $context['_parent'] = $context;
            $context['_seq'] = CoreExtension::ensureTraversable(CoreExtension::getAttribute($this->env, $this->source, ($context["item"] ?? null), "overlap", [], "any", false, false, false, 86));
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
            foreach ($context['_seq'] as $context["_key"] => $context["overlap"]) {
                // line 87
                yield "            <div class=\"relative w-full\">
                ";
                // line 88
                yield Twig\Extension\CoreExtension::include($this->env, $context, "ui/timetableItem.twig.html", ["item" => $context["overlap"], "overlap" => true]);
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
            unset($context['_seq'], $context['_iterated'], $context['_key'], $context['overlap'], $context['_parent'], $context['loop']);
            $context = array_intersect_key($context, $_parent) + $_parent;
            // line 91
            yield "    </div>
    ";
        }
        // line 93
        yield "
    ";
        // line 94
        if ((($context["format"] ?? null) != "print")) {
            // line 95
            yield "        ";
            $context["iconSize"] = (((($context["duration"] ?? null) >= 50)) ? ("size-6") : ((((($context["duration"] ?? null) >= 30)) ? ("size-5") : ("size-3"))));
            // line 96
            yield "
        ";
            // line 97
            if ((CoreExtension::getAttribute($this->env, $this->source, ($context["item"] ?? null), "primaryAction", [], "any", false, false, false, 97) && (($context["duration"] ?? null) >= 15))) {
                // line 98
                yield "            ";
                $context["action"] = CoreExtension::getAttribute($this->env, $this->source, ($context["item"] ?? null), "primaryAction", [], "any", false, false, false, 98);
                // line 99
                yield "            ";
                $context["iconClass"] = ((CoreExtension::getAttribute($this->env, $this->source, ($context["action"] ?? null), "iconClass", [], "any", false, false, false, 99)) ? (CoreExtension::getAttribute($this->env, $this->source, ($context["action"] ?? null), "iconClass", [], "any", false, false, false, 99)) : ("text-gray-600 hover:text-gray-800"));
                // line 100
                yield "            
            <a href=\"";
                // line 101
                yield $this->env->getRuntime('Twig\Runtime\EscaperRuntime')->escape(CoreExtension::getAttribute($this->env, $this->source, ($context["action"] ?? null), "url", [], "any", false, false, false, 101), "html", null, true);
                yield "\" class=\"absolute bottom-0 right-0 mr-1 cursor-pointer pointer-events-auto\" title=\"";
                yield $this->env->getRuntime('Twig\Runtime\EscaperRuntime')->escape(CoreExtension::getAttribute($this->env, $this->source, ($context["action"] ?? null), "label", [], "any", false, false, false, 101), "html", null, true);
                yield "\">
                ";
                // line 102
                yield $this->env->getFunction('icon')->getCallable()((((CoreExtension::getAttribute($this->env, $this->source, ($context["action"] ?? null), "iconLibrary", [], "any", true, true, false, 102) &&  !(null === CoreExtension::getAttribute($this->env, $this->source, ($context["action"] ?? null), "iconLibrary", [], "any", false, false, false, 102)))) ? (CoreExtension::getAttribute($this->env, $this->source, ($context["action"] ?? null), "iconLibrary", [], "any", false, false, false, 102)) : ("solid")), CoreExtension::getAttribute($this->env, $this->source, ($context["action"] ?? null), "icon", [], "any", false, false, false, 102), ((($context["iconSize"] ?? null) . " ") . ($context["iconClass"] ?? null)));
                yield "
            </a>
        ";
            }
            // line 105
            yield "
        ";
            // line 106
            if ((CoreExtension::getAttribute($this->env, $this->source, ($context["item"] ?? null), "secondaryAction", [], "any", false, false, false, 106) && (($context["duration"] ?? null) >= 15))) {
                // line 107
                yield "            ";
                $context["action"] = CoreExtension::getAttribute($this->env, $this->source, ($context["item"] ?? null), "secondaryAction", [], "any", false, false, false, 107);
                // line 108
                yield "            ";
                $context["iconClass"] = ((CoreExtension::getAttribute($this->env, $this->source, ($context["action"] ?? null), "iconClass", [], "any", false, false, false, 108)) ? (CoreExtension::getAttribute($this->env, $this->source, ($context["action"] ?? null), "iconClass", [], "any", false, false, false, 108)) : ("text-gray-600 hover:text-gray-800"));
                // line 109
                yield "
            <a href=\"";
                // line 110
                yield $this->env->getRuntime('Twig\Runtime\EscaperRuntime')->escape(CoreExtension::getAttribute($this->env, $this->source, ($context["action"] ?? null), "url", [], "any", false, false, false, 110), "html", null, true);
                yield "\" class=\"absolute bottom-0 left-0 ml-1 cursor-pointer pointer-events-auto\" title=\"";
                yield $this->env->getRuntime('Twig\Runtime\EscaperRuntime')->escape(CoreExtension::getAttribute($this->env, $this->source, ($context["action"] ?? null), "label", [], "any", false, false, false, 110), "html", null, true);
                yield "\">
                ";
                // line 111
                yield $this->env->getFunction('icon')->getCallable()((((CoreExtension::getAttribute($this->env, $this->source, ($context["action"] ?? null), "iconLibrary", [], "any", true, true, false, 111) &&  !(null === CoreExtension::getAttribute($this->env, $this->source, ($context["action"] ?? null), "iconLibrary", [], "any", false, false, false, 111)))) ? (CoreExtension::getAttribute($this->env, $this->source, ($context["action"] ?? null), "iconLibrary", [], "any", false, false, false, 111)) : ("solid")), CoreExtension::getAttribute($this->env, $this->source, ($context["action"] ?? null), "icon", [], "any", false, false, false, 111), ((($context["iconSize"] ?? null) . " ") . ($context["iconClass"] ?? null)));
                yield "
            </a>
        ";
            }
            // line 114
            yield "
        ";
            // line 115
            if (((CoreExtension::getAttribute($this->env, $this->source, ($context["item"] ?? null), "hasStatus", ["overlap"], "method", false, false, false, 115) && (($context["duration"] ?? null) >= 40)) &&  !CoreExtension::getAttribute($this->env, $this->source, ($context["item"] ?? null), "secondaryAction", [], "any", false, false, false, 115))) {
                // line 116
                yield "        <div href=\"";
                yield $this->env->getRuntime('Twig\Runtime\EscaperRuntime')->escape(CoreExtension::getAttribute($this->env, $this->source, ($context["action"] ?? null), "url", [], "any", false, false, false, 116), "html", null, true);
                yield "\" class=\"absolute bottom-0 left-0 ml-1\" title=\"";
                yield $this->env->getRuntime('Twig\Runtime\EscaperRuntime')->escape($this->env->getFunction('__')->getCallable()("Click & drag to see overlapping items"), "html", null, true);
                yield "\">
            ";
                // line 117
                yield $this->env->getFunction('icon')->getCallable()("outline", "layers", "size-5 text-gray-500 hover:text-gray-600");
                yield "
        </div>
        ";
            }
            // line 120
            yield "    ";
        }
        // line 121
        yield "</div>
";
        return; yield '';
    }

    /**
     * @codeCoverageIgnore
     */
    public function getTemplateName()
    {
        return "ui/timetableItem.twig.html";
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
        return array (  386 => 121,  383 => 120,  377 => 117,  370 => 116,  368 => 115,  365 => 114,  359 => 111,  353 => 110,  350 => 109,  347 => 108,  344 => 107,  342 => 106,  339 => 105,  333 => 102,  327 => 101,  324 => 100,  321 => 99,  318 => 98,  316 => 97,  313 => 96,  310 => 95,  308 => 94,  305 => 93,  301 => 91,  284 => 88,  281 => 87,  264 => 86,  257 => 82,  251 => 79,  247 => 77,  245 => 76,  241 => 74,  235 => 71,  232 => 70,  226 => 68,  224 => 67,  219 => 66,  217 => 65,  214 => 64,  208 => 61,  201 => 60,  199 => 59,  189 => 58,  185 => 56,  181 => 54,  173 => 52,  171 => 51,  167 => 50,  164 => 49,  162 => 48,  157 => 45,  149 => 42,  144 => 40,  138 => 39,  134 => 37,  132 => 36,  125 => 32,  121 => 31,  115 => 30,  108 => 26,  93 => 24,  89 => 23,  80 => 17,  70 => 16,  50 => 15,  47 => 14,  45 => 13,  43 => 12,  41 => 11,  38 => 10,);
    }

    public function getSourceContext()
    {
        return new Source("", "ui/timetableItem.twig.html", "/var/www/html/gibbon/resources/templates/ui/timetableItem.twig.html");
    }
}
