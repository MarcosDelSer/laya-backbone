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

/* ui/timetableNav.twig.html */
class __TwigTemplate_e213aec1c9fddb72be3473ac3858f1d8 extends Template
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
<form
    hx-post='";
        // line 12
        yield $this->env->getRuntime('Twig\Runtime\EscaperRuntime')->escape(($context["apiEndpoint"] ?? null), "html", null, true);
        yield "' 
    hx-trigger='click from:(button.ttNav), change from:(#ttDateChooser)'
    hx-target='closest #timetable' 
    hx-select='#timetable'
    hx-swap='outerHTML' 
    hx-indicator='#indicator'
    hx-include='[name=\"ttDateChooser\"],[name=\"ttCalendarRefresh\"],[name=\"gibbonTTID\"]''
    hx-vals='js:{\"edit\": \"";
        // line 19
        yield $this->env->getRuntime('Twig\Runtime\EscaperRuntime')->escape(($context["edit"] ?? null), "html", null, true);
        yield "\"}'
    hx-disinherit=\"*\"
>
    <nav id='#ttNav' cellspacing='0' class='flex justify-between items-end w-full my-2'>
    <input type='hidden' name='ttDateNav' x-model='ttDate'>
    <input type='hidden' name='ttCalendarRefresh' x-model='ttRefresh'>
    <input type='hidden' name='gibbonTTID' x-model='ttID'>

    <div x-data=\"{layersOpen: false, calendarsOpen: false, timetablesOpen: false, optionsOpen: false}\" class=\" flex items-start\">
    
        ";
        // line 29
        if ((Twig\Extension\CoreExtension::length($this->env->getCharset(), ($context["timetables"] ?? null)) > 1)) {
            // line 30
            yield "        <div class=\"relative\">
            <button type='button' class='inline-flex items-center align-middle rounded-l h-8 px-4 text-xs/6 border border-gray-400 text-gray-600 bg-gray-100 font-medium hover:bg-gray-300 hover:text-gray-700' @click=\"timetablesOpen = !timetablesOpen\" :class=\"{'bg-gray-300 text-gray-700': timetablesOpen}\">
            ";
            // line 32
            yield $this->env->getFunction('icon')->getCallable()("outline", "squares", "inline-block size-4", ["stroke-width" => 2]);
            yield "
                <span class='hidden md:inline ml-2'>";
            // line 33
            yield $this->env->getRuntime('Twig\Runtime\EscaperRuntime')->escape($this->env->getFunction('__')->getCallable()("Timetables"), "html", null, true);
            yield "</span>
            </button>

            <div x-cloak x-transition.opacity x-show=\"timetablesOpen\" @click.outside=\"timetablesOpen = false\" class=\"absolute min-w-48 -mt-px z-20 flex flex-col gap-2 items-start justify-end rounded border bg-white shadow-lg px-3 py-4\">
                ";
            // line 37
            $context['_parent'] = $context;
            $context['_seq'] = CoreExtension::ensureTraversable(($context["timetables"] ?? null));
            foreach ($context['_seq'] as $context["timetableID"] => $context["timetableName"]) {
                // line 38
                yield "                
                <button type='button' class=\"ttNav inline-flex items-center gap-2 px-1 text-gray-600 hover:text-gray-800 text-sm\" @click=\"ttID = '";
                // line 39
                yield $this->env->getRuntime('Twig\Runtime\EscaperRuntime')->escape($context["timetableID"], "html", null, true);
                yield "'\">
                    <input type=\"radio\" x-model=\"ttID\" value=\"";
                // line 40
                yield $this->env->getRuntime('Twig\Runtime\EscaperRuntime')->escape($context["timetableID"], "html", null, true);
                yield "\" class=\"border ";
                yield $this->env->getRuntime('Twig\Runtime\EscaperRuntime')->escape(CoreExtension::getAttribute($this->env, $this->source, ($context["color"] ?? null), "background", [], "any", false, false, false, 40), "html", null, true);
                yield " ";
                yield $this->env->getRuntime('Twig\Runtime\EscaperRuntime')->escape(CoreExtension::getAttribute($this->env, $this->source, ($context["color"] ?? null), "textLight", [], "any", false, false, false, 40), "html", null, true);
                yield "\" >
                    <label class=\"select-none whitespace-nowrap\">
                    ";
                // line 42
                yield $this->env->getRuntime('Twig\Runtime\EscaperRuntime')->escape($context["timetableName"], "html", null, true);
                yield "
                    </label>
                </button>
                ";
            }
            $_parent = $context['_parent'];
            unset($context['_seq'], $context['_iterated'], $context['timetableID'], $context['timetableName'], $context['_parent'], $context['loop']);
            $context = array_intersect_key($context, $_parent) + $_parent;
            // line 46
            yield "            </div>
        </div>
        ";
        }
        // line 49
        yield "
        <div class=\"relative\">
            <button type='button' class='inline-flex items-center align-middle  h-8 px-4 text-xs/6 border border-gray-400 text-gray-600 bg-gray-100 font-medium hover:bg-gray-300 hover:text-gray-700 ";
        // line 51
        yield (((Twig\Extension\CoreExtension::length($this->env->getCharset(), ($context["timetables"] ?? null)) > 1)) ? ("-ml-px") : ("rounded-l"));
        yield "' @click=\"layersOpen = !layersOpen\" :class=\"{'bg-gray-300 text-gray-700': layersOpen}\">
            ";
        // line 52
        yield $this->env->getFunction('icon')->getCallable()("outline", "layers", "inline-block size-4");
        yield "
                <span class='hidden md:inline ml-2'>";
        // line 53
        yield $this->env->getRuntime('Twig\Runtime\EscaperRuntime')->escape($this->env->getFunction('__')->getCallable()("Layers"), "html", null, true);
        yield "</span>
            </button>

            <div x-cloak x-transition.opacity x-show=\"layersOpen\" @click.outside=\"layersOpen = false\" class=\"absolute min-w-48 -ml-px -mt-px z-20 flex flex-col justify-end rounded border bg-white shadow-lg p-3\">
                ";
        // line 57
        $context['_parent'] = $context;
        $context['_seq'] = CoreExtension::ensureTraversable(Twig\Extension\CoreExtension::reverse($this->env->getCharset(), ($context["layersList"] ?? null)));
        foreach ($context['_seq'] as $context["_key"] => $context["layer"]) {
            // line 58
            yield "                    
                    ";
            // line 59
            $context["color"] = CoreExtension::getAttribute($this->env, $this->source, ($context["structure"] ?? null), "getColors", [CoreExtension::getAttribute($this->env, $this->source, $context["layer"], "getColor", [], "any", false, false, false, 59)], "method", false, false, false, 59);
            // line 60
            yield "
                    <div class=\"p-1 flex gap-2 items-center text-gray-700 cursor-pointer mr-4\" @click=\"";
            // line 61
            yield $this->env->getRuntime('Twig\Runtime\EscaperRuntime')->escape(CoreExtension::getAttribute($this->env, $this->source, $context["layer"], "getID", [], "any", false, false, false, 61), "html", null, true);
            yield " = !";
            yield $this->env->getRuntime('Twig\Runtime\EscaperRuntime')->escape(CoreExtension::getAttribute($this->env, $this->source, $context["layer"], "getID", [], "any", false, false, false, 61), "html", null, true);
            yield "\" hx-get=\"";
            yield $this->env->getRuntime('Twig\Runtime\EscaperRuntime')->escape(($context["preferencesUrl"] ?? null), "html", null, true);
            yield "\" hx-target=\"this\" hx-trigger=\"click consume\" hx-swap=\"none\" hx-include=\"[name='";
            yield $this->env->getRuntime('Twig\Runtime\EscaperRuntime')->escape(CoreExtension::getAttribute($this->env, $this->source, $context["layer"], "getID", [], "any", false, false, false, 61), "html", null, true);
            yield "']\" hx-vals='{\"scope\": \"ttLayers\", \"key\": \"";
            yield $this->env->getRuntime('Twig\Runtime\EscaperRuntime')->escape(CoreExtension::getAttribute($this->env, $this->source, $context["layer"], "getID", [], "any", false, false, false, 61), "html", null, true);
            yield "\", \"default\": 0}' >
                        <input type=\"checkbox\" x-model=\"";
            // line 62
            yield $this->env->getRuntime('Twig\Runtime\EscaperRuntime')->escape(CoreExtension::getAttribute($this->env, $this->source, $context["layer"], "getID", [], "any", false, false, false, 62), "html", null, true);
            yield "\" name=\"";
            yield $this->env->getRuntime('Twig\Runtime\EscaperRuntime')->escape(CoreExtension::getAttribute($this->env, $this->source, $context["layer"], "getID", [], "any", false, false, false, 62), "html", null, true);
            yield "\" class=\"border p-1 ";
            yield $this->env->getRuntime('Twig\Runtime\EscaperRuntime')->escape(CoreExtension::getAttribute($this->env, $this->source, ($context["color"] ?? null), "background", [], "any", false, false, false, 62), "html", null, true);
            yield " ";
            yield $this->env->getRuntime('Twig\Runtime\EscaperRuntime')->escape(CoreExtension::getAttribute($this->env, $this->source, ($context["color"] ?? null), "textLight", [], "any", false, false, false, 62), "html", null, true);
            yield "\" checked=\"";
            yield $this->env->getRuntime('Twig\Runtime\EscaperRuntime')->escape(CoreExtension::getAttribute($this->env, $this->source, $context["layer"], "getID", [], "any", false, false, false, 62), "html", null, true);
            yield "\" >
                        <label class=\"select-none whitespace-nowrap text-sm\" :class=\"{'line-through opacity-50': !";
            // line 63
            yield $this->env->getRuntime('Twig\Runtime\EscaperRuntime')->escape(CoreExtension::getAttribute($this->env, $this->source, $context["layer"], "getID", [], "any", false, false, false, 63), "html", null, true);
            yield " }\" for=\"";
            yield $this->env->getRuntime('Twig\Runtime\EscaperRuntime')->escape(CoreExtension::getAttribute($this->env, $this->source, $context["layer"], "getID", [], "any", false, false, false, 63), "html", null, true);
            yield "\">
                            ";
            // line 64
            yield $this->env->getRuntime('Twig\Runtime\EscaperRuntime')->escape(CoreExtension::getAttribute($this->env, $this->source, $context["layer"], "getName", [], "any", false, false, false, 64), "html", null, true);
            yield "<span class=\"inline-block ml-2 text-gray-400 text-xs\">(";
            yield $this->env->getRuntime('Twig\Runtime\EscaperRuntime')->escape(CoreExtension::getAttribute($this->env, $this->source, $context["layer"], "countItems", [], "any", false, false, false, 64), "html", null, true);
            yield ") </span>
                        </label>
                    </div>
                    
                ";
        }
        $_parent = $context['_parent'];
        unset($context['_seq'], $context['_iterated'], $context['_key'], $context['layer'], $context['_parent'], $context['loop']);
        $context = array_intersect_key($context, $_parent) + $_parent;
        // line 69
        yield "
                ";
        // line 70
        if (($context["gibbonPersonID"] ?? null)) {
            // line 71
            yield "                <div class=\"-mx-3 mt-2 -mb-3 border-t border-gray-400\">
                    <a hx-boost=\"true\" 
                    hx-target=\"#modalContent\"
                    hx-push-url=\"false\"
                    x-on:htmx:after-on-load=\"modalOpen = true\"
                    x-on:click=\"modalType = 'delete'\"
                    hx-swap=\"innerHTML show:no-scroll swap:0s\" 
                    
                    href=\"";
            // line 79
            yield $this->env->getRuntime('Twig\Runtime\EscaperRuntime')->escape(($context["absoluteURL"] ?? null), "html", null, true);
            yield "/index_tt_layers.php\" class=\"flex justify-start items-center align-middle select-none whitespace-nowrap text-sm px-4 py-2 gap-2 text-gray-700 hover:text-gray-800 hover:bg-gray-200 rounded-b\">
                        ";
            // line 80
            yield $this->env->getFunction('icon')->getCallable()("basic", "menu", "inline-block size-5 text-gray-600 mt-px -ml-px");
            yield "
                        ";
            // line 81
            yield $this->env->getRuntime('Twig\Runtime\EscaperRuntime')->escape($this->env->getFunction('__')->getCallable()("Reorder Layers"), "html", null, true);
            yield "
                    </a>
                </div>
                ";
        }
        // line 85
        yield "            </div>
        </div>

        ";
        // line 88
        if (($context["calendars"] ?? null)) {
            // line 89
            yield "        <div class=\"relative\">
            <button type='button' class='inline-flex items-center align-middle  h-8 px-4 text-xs/6 border border-gray-400 text-gray-600 bg-gray-100 font-medium hover:bg-gray-300 hover:text-gray-700 -ml-px' @click=\"calendarsOpen = !calendarsOpen\" :class=\"{'bg-gray-300 text-gray-700': calendarsOpen}\">
            ";
            // line 91
            yield $this->env->getFunction('icon')->getCallable()("solid", "calendar", "inline-block size-4");
            yield "
                <span class='hidden md:inline ml-2'>";
            // line 92
            yield $this->env->getRuntime('Twig\Runtime\EscaperRuntime')->escape($this->env->getFunction('__')->getCallable()("Calendars"), "html", null, true);
            yield "</span>
            </button>

            <div x-cloak x-transition.opacity x-show=\"calendarsOpen\" @click.outside=\"calendarsOpen = false\" class=\"absolute min-w-48 -ml-px -mt-px z-20 flex flex-col justify-end rounded border bg-white shadow-lg p-3\">
                ";
            // line 96
            $context['_parent'] = $context;
            $context['_seq'] = CoreExtension::ensureTraversable(Twig\Extension\CoreExtension::reverse($this->env->getCharset(), ($context["calendars"] ?? null)));
            foreach ($context['_seq'] as $context["_key"] => $context["calendar"]) {
                // line 97
                yield "                    
                    ";
                // line 98
                $context["color"] = CoreExtension::getAttribute($this->env, $this->source, ($context["structure"] ?? null), "getColors", [CoreExtension::getAttribute($this->env, $this->source, $context["calendar"], "getColor", [], "any", false, false, false, 98)], "method", false, false, false, 98);
                // line 99
                yield "
                    <div class=\"p-1 flex gap-2 items-center text-gray-700 cursor-pointer mr-4\" @click=\"";
                // line 100
                yield $this->env->getRuntime('Twig\Runtime\EscaperRuntime')->escape(CoreExtension::getAttribute($this->env, $this->source, $context["calendar"], "getID", [], "any", false, false, false, 100), "html", null, true);
                yield " = !";
                yield $this->env->getRuntime('Twig\Runtime\EscaperRuntime')->escape(CoreExtension::getAttribute($this->env, $this->source, $context["calendar"], "getID", [], "any", false, false, false, 100), "html", null, true);
                yield "\" hx-get=\"";
                yield $this->env->getRuntime('Twig\Runtime\EscaperRuntime')->escape(($context["preferencesUrl"] ?? null), "html", null, true);
                yield "\" hx-target=\"this\" hx-trigger=\"click consume\" hx-swap=\"none\" hx-include=\"[name='";
                yield $this->env->getRuntime('Twig\Runtime\EscaperRuntime')->escape(CoreExtension::getAttribute($this->env, $this->source, $context["calendar"], "getID", [], "any", false, false, false, 100), "html", null, true);
                yield "']\" hx-vals='{\"scope\": \"ttLayers\", \"key\": \"";
                yield $this->env->getRuntime('Twig\Runtime\EscaperRuntime')->escape(CoreExtension::getAttribute($this->env, $this->source, $context["calendar"], "getID", [], "any", false, false, false, 100), "html", null, true);
                yield "\", \"default\": 0}' >
                        <input type=\"checkbox\" x-model=\"";
                // line 101
                yield $this->env->getRuntime('Twig\Runtime\EscaperRuntime')->escape(CoreExtension::getAttribute($this->env, $this->source, $context["calendar"], "getID", [], "any", false, false, false, 101), "html", null, true);
                yield "\" name=\"";
                yield $this->env->getRuntime('Twig\Runtime\EscaperRuntime')->escape(CoreExtension::getAttribute($this->env, $this->source, $context["calendar"], "getID", [], "any", false, false, false, 101), "html", null, true);
                yield "\" class=\"border p-1 ";
                yield $this->env->getRuntime('Twig\Runtime\EscaperRuntime')->escape(CoreExtension::getAttribute($this->env, $this->source, ($context["color"] ?? null), "background", [], "any", false, false, false, 101), "html", null, true);
                yield " ";
                yield $this->env->getRuntime('Twig\Runtime\EscaperRuntime')->escape(CoreExtension::getAttribute($this->env, $this->source, ($context["color"] ?? null), "textLight", [], "any", false, false, false, 101), "html", null, true);
                yield "\" checked=\"";
                yield $this->env->getRuntime('Twig\Runtime\EscaperRuntime')->escape(CoreExtension::getAttribute($this->env, $this->source, $context["calendar"], "getID", [], "any", false, false, false, 101), "html", null, true);
                yield "\" style=\"";
                yield $this->env->getRuntime('Twig\Runtime\EscaperRuntime')->escape(CoreExtension::getAttribute($this->env, $this->source, ($context["color"] ?? null), "bgStyle", [], "any", false, false, false, 101), "html", null, true);
                yield "\" >
                        <label class=\"select-none whitespace-nowrap text-sm\" :class=\"{'line-through opacity-50': !";
                // line 102
                yield $this->env->getRuntime('Twig\Runtime\EscaperRuntime')->escape(CoreExtension::getAttribute($this->env, $this->source, $context["calendar"], "getID", [], "any", false, false, false, 102), "html", null, true);
                yield " }\" for=\"";
                yield $this->env->getRuntime('Twig\Runtime\EscaperRuntime')->escape(CoreExtension::getAttribute($this->env, $this->source, $context["calendar"], "getID", [], "any", false, false, false, 102), "html", null, true);
                yield "\">
                            ";
                // line 103
                yield $this->env->getRuntime('Twig\Runtime\EscaperRuntime')->escape(CoreExtension::getAttribute($this->env, $this->source, $context["calendar"], "getName", [], "any", false, false, false, 103), "html", null, true);
                yield "<span class=\"inline-block ml-2 text-gray-400 text-xs\">(";
                yield $this->env->getRuntime('Twig\Runtime\EscaperRuntime')->escape(CoreExtension::getAttribute($this->env, $this->source, $context["calendar"], "countItems", [], "any", false, false, false, 103), "html", null, true);
                yield ") </span>
                        </label>
                    </div>
                    
                ";
            }
            $_parent = $context['_parent'];
            unset($context['_seq'], $context['_iterated'], $context['_key'], $context['calendar'], $context['_parent'], $context['loop']);
            $context = array_intersect_key($context, $_parent) + $_parent;
            // line 108
            yield "
                ";
            // line 109
            if (($context["gibbonPersonID"] ?? null)) {
                // line 110
                yield "                <nav class=\"-mx-3 mt-2 -mb-3 border-t border-gray-400\">
                    ";
                // line 111
                $context["optionID"] = "onlyMyEvents";
                // line 112
                yield "
                    <div class=\"px-4 py-2 flex gap-2 items-center text-gray-700 hover:text-gray-800 cursor-pointer mr-4\" @click=\"";
                // line 113
                yield $this->env->getRuntime('Twig\Runtime\EscaperRuntime')->escape(($context["optionID"] ?? null), "html", null, true);
                yield " = !";
                yield $this->env->getRuntime('Twig\Runtime\EscaperRuntime')->escape(($context["optionID"] ?? null), "html", null, true);
                yield "\" hx-get=\"";
                yield $this->env->getRuntime('Twig\Runtime\EscaperRuntime')->escape(($context["preferencesUrl"] ?? null), "html", null, true);
                yield "\" hx-target=\"this\" hx-trigger=\"click consume\" hx-swap=\"none\" hx-include=\"[name='";
                yield $this->env->getRuntime('Twig\Runtime\EscaperRuntime')->escape(($context["optionID"] ?? null), "html", null, true);
                yield "']\" hx-vals='{\"scope\": \"ttOptions\", \"key\": \"";
                yield $this->env->getRuntime('Twig\Runtime\EscaperRuntime')->escape(($context["optionID"] ?? null), "html", null, true);
                yield "\", \"default\": 0}' >
                        <input type=\"checkbox\" x-model=\"";
                // line 114
                yield $this->env->getRuntime('Twig\Runtime\EscaperRuntime')->escape(($context["optionID"] ?? null), "html", null, true);
                yield "\" name=\"";
                yield $this->env->getRuntime('Twig\Runtime\EscaperRuntime')->escape(($context["optionID"] ?? null), "html", null, true);
                yield "\" class=\"border p-1 ";
                yield $this->env->getRuntime('Twig\Runtime\EscaperRuntime')->escape(CoreExtension::getAttribute($this->env, $this->source, ($context["color"] ?? null), "background", [], "any", false, false, false, 114), "html", null, true);
                yield " ";
                yield $this->env->getRuntime('Twig\Runtime\EscaperRuntime')->escape(CoreExtension::getAttribute($this->env, $this->source, ($context["color"] ?? null), "textLight", [], "any", false, false, false, 114), "html", null, true);
                yield "\" value=\"1\" >
                        <label class=\"select-none whitespace-nowrap text-sm\" for=\"";
                // line 115
                yield $this->env->getRuntime('Twig\Runtime\EscaperRuntime')->escape(($context["optionID"] ?? null), "html", null, true);
                yield "\">
                            ";
                // line 116
                yield $this->env->getRuntime('Twig\Runtime\EscaperRuntime')->escape($this->env->getFunction('__')->getCallable()("Only Show My Events"), "html", null, true);
                yield "
                        </label>
                    </div>

                </nav>
                ";
            }
            // line 122
            yield "            </div>
            
        </div>
        ";
        }
        // line 126
        yield "
        <div class=\"relative\">
            <button type='button' class='inline-flex items-center align-middle h-8 px-2 text-xs/6 border border-gray-400 text-gray-600 bg-gray-100 font-medium hover:bg-gray-300 hover:text-gray-700 ";
        // line 128
        yield (((Twig\Extension\CoreExtension::length($this->env->getCharset(), ($context["layers"] ?? null)) > 1)) ? ("rounded-r -ml-px") : ("rounded"));
        yield "' @click=\"optionsOpen = !optionsOpen\" :class=\"{'bg-gray-300 text-gray-700': optionsOpen}\">
                ";
        // line 129
        yield $this->env->getFunction('icon')->getCallable()("basic", "ellipsis-vertical", "inline-block size-5");
        yield "
            </button>

            <div x-cloak x-transition.opacity x-show=\"optionsOpen\" @click.outside=\"optionsOpen = false\" class=\"absolute min-w-48 -ml-px -mt-px z-20 flex flex-col justify-end rounded border bg-white shadow-lg \">
                
                <nav class=\"py-1\">
                    ";
        // line 135
        if ((($context["gibbonPersonID"] ?? null) || ($context["gibbonSpaceID"] ?? null))) {
            // line 136
            yield "                    <a href=\"";
            yield $this->env->getRuntime('Twig\Runtime\EscaperRuntime')->escape(($context["absoluteURL"] ?? null), "html", null, true);
            yield "/report.php?q=/modules/Timetable/";
            yield $this->env->getRuntime('Twig\Runtime\EscaperRuntime')->escape(((($context["gibbonPersonID"] ?? null)) ? (("tt_view.php&gibbonPersonID=" . ($context["gibbonPersonID"] ?? null))) : (("tt_space_view.php&gibbonSpaceID=" . ($context["gibbonSpaceID"] ?? null)))), "html", null, true);
            yield "&ttDate=";
            yield $this->env->getRuntime('Twig\Runtime\EscaperRuntime')->escape(CoreExtension::getAttribute($this->env, $this->source, CoreExtension::getAttribute($this->env, $this->source, ($context["structure"] ?? null), "getCurrentDate", [], "any", false, false, false, 136), "format", ["Y-m-d"], "method", false, false, false, 136), "html", null, true);
            yield "&format=print&hideHeader=true\" target=\"_blank\" class=\"flex justify-start items-center align-middle select-none whitespace-nowrap text-sm px-3 py-1.5 gap-3 text-gray-700 hover:text-gray-800 hover:bg-gray-200\">
                        ";
            // line 137
            yield $this->env->getFunction('icon')->getCallable()("solid", "print", "inline-block size-5 text-gray-600");
            yield "
                        ";
            // line 138
            yield $this->env->getRuntime('Twig\Runtime\EscaperRuntime')->escape($this->env->getFunction('__')->getCallable()("Print"), "html", null, true);
            yield "
                    </a>
                    
                    <a href=\"";
            // line 141
            yield $this->env->getRuntime('Twig\Runtime\EscaperRuntime')->escape(($context["absoluteURL"] ?? null), "html", null, true);
            yield "/index.php?q=/modules/Timetable/";
            yield $this->env->getRuntime('Twig\Runtime\EscaperRuntime')->escape(((($context["gibbonPersonID"] ?? null)) ? (("tt_view.php&gibbonPersonID=" . ($context["gibbonPersonID"] ?? null))) : (("tt_space_view.php&gibbonSpaceID=" . ($context["gibbonSpaceID"] ?? null)))), "html", null, true);
            yield "&ttDate=";
            yield $this->env->getRuntime('Twig\Runtime\EscaperRuntime')->escape(CoreExtension::getAttribute($this->env, $this->source, CoreExtension::getAttribute($this->env, $this->source, ($context["structure"] ?? null), "getCurrentDate", [], "any", false, false, false, 141), "format", ["Y-m-d"], "method", false, false, false, 141), "html", null, true);
            yield "\" target=\"_blank\" class=\"flex justify-start items-center align-middle select-none whitespace-nowrap text-sm px-3 py-1.5 gap-3 text-gray-700 hover:text-gray-800 hover:bg-gray-200\">
                        ";
            // line 142
            yield $this->env->getFunction('icon')->getCallable()("solid", "external-link", "inline-block size-5 text-gray-600");
            yield "
                        ";
            // line 143
            yield $this->env->getRuntime('Twig\Runtime\EscaperRuntime')->escape($this->env->getFunction('__')->getCallable()("Open"), "html", null, true);
            yield "
                    </a>

                    ";
        }
        // line 147
        yield "                </nav>

                <nav class=\"py-1 px-2 border-t border-gray-400\">
                    ";
        // line 150
        $context["optionID"] = "showCurrentTime";
        // line 151
        yield "
                    <div class=\"p-1 flex gap-3 items-center text-gray-600 hover:text-gray-800 cursor-pointer mr-4\" @click=\"";
        // line 152
        yield $this->env->getRuntime('Twig\Runtime\EscaperRuntime')->escape(($context["optionID"] ?? null), "html", null, true);
        yield " = !";
        yield $this->env->getRuntime('Twig\Runtime\EscaperRuntime')->escape(($context["optionID"] ?? null), "html", null, true);
        yield "\" hx-get=\"";
        yield $this->env->getRuntime('Twig\Runtime\EscaperRuntime')->escape(($context["preferencesUrl"] ?? null), "html", null, true);
        yield "\" hx-target=\"this\" hx-trigger=\"click consume\" hx-swap=\"none\" hx-include=\"[name='";
        yield $this->env->getRuntime('Twig\Runtime\EscaperRuntime')->escape(($context["optionID"] ?? null), "html", null, true);
        yield "']\" hx-vals='{\"scope\": \"ttOptions\", \"key\": \"";
        yield $this->env->getRuntime('Twig\Runtime\EscaperRuntime')->escape(($context["optionID"] ?? null), "html", null, true);
        yield "\", \"default\": 0}' >
                        <input type=\"checkbox\" x-model=\"";
        // line 153
        yield $this->env->getRuntime('Twig\Runtime\EscaperRuntime')->escape(($context["optionID"] ?? null), "html", null, true);
        yield "\" name=\"";
        yield $this->env->getRuntime('Twig\Runtime\EscaperRuntime')->escape(($context["optionID"] ?? null), "html", null, true);
        yield "\" class=\"border mx-0.5 p-1 ";
        yield $this->env->getRuntime('Twig\Runtime\EscaperRuntime')->escape(CoreExtension::getAttribute($this->env, $this->source, ($context["color"] ?? null), "background", [], "any", false, false, false, 153), "html", null, true);
        yield " ";
        yield $this->env->getRuntime('Twig\Runtime\EscaperRuntime')->escape(CoreExtension::getAttribute($this->env, $this->source, ($context["color"] ?? null), "textLight", [], "any", false, false, false, 153), "html", null, true);
        yield "\" value=\"1\" >
                        <label class=\"select-none whitespace-nowrap text-sm\" for=\"";
        // line 154
        yield $this->env->getRuntime('Twig\Runtime\EscaperRuntime')->escape(($context["optionID"] ?? null), "html", null, true);
        yield "\">
                            ";
        // line 155
        yield $this->env->getRuntime('Twig\Runtime\EscaperRuntime')->escape($this->env->getFunction('__')->getCallable()("Show Current Time"), "html", null, true);
        yield "
                        </label>
                    </div>

                </nav>
                
            </div>
        </div>

    </div>

    <div class=\"flex-shrink flex\">
    
        <button type='button' class='ttNav inline-flex items-center align-middle rounded-l h-8 px-3 text-xs/6 border border-gray-400 text-gray-600 bg-gray-100 font-medium hover:bg-gray-300 hover:text-gray-700'
        x-on:click='ttDate=\"";
        // line 169
        yield $this->env->getRuntime('Twig\Runtime\EscaperRuntime')->escape($this->extensions['Twig\Extension\CoreExtension']->formatDate(CoreExtension::getAttribute($this->env, $this->source, CoreExtension::getAttribute($this->env, $this->source, ($context["structure"] ?? null), "getCurrentDate", [], "any", false, false, false, 169), "modify", ["-1 week"], "method", false, false, false, 169), "Y-m-d"), "html", null, true);
        yield "\"'>
        ";
        // line 170
        yield $this->env->getFunction('icon')->getCallable()("basic", "chevron-left", "inline-block size-5");
        yield "
        <span class='hidden sm:inline sr-only'>";
        // line 171
        yield $this->env->getRuntime('Twig\Runtime\EscaperRuntime')->escape($this->env->getFunction('__')->getCallable()("Last Week"), "html", null, true);
        yield "</span></button>

        <button type='button' class='ttNav inline-flex items-center align-middle h-8 px-4 text-xs/6 border border-gray-400 text-gray-600 bg-gray-100 font-medium hover:bg-gray-300 hover:text-gray-700 -ml-px'
            x-on:click='ttDate=\"";
        // line 174
        yield $this->env->getRuntime('Twig\Runtime\EscaperRuntime')->escape(CoreExtension::getAttribute($this->env, $this->source, CoreExtension::getAttribute($this->env, $this->source, ($context["structure"] ?? null), "today", [], "any", false, false, false, 174), "format", ["Y-m-d"], "method", false, false, false, 174), "html", null, true);
        yield "\"' title=\"";
        yield $this->env->getRuntime('Twig\Runtime\EscaperRuntime')->escape($this->env->getFunction('__')->getCallable()("This Week"), "html", null, true);
        yield "\">
            ";
        // line 175
        yield $this->env->getFunction('icon')->getCallable()("basic", "home", "inline-block size-4");
        yield "
            <span class='hidden sm:inline sr-only'>";
        // line 176
        yield $this->env->getRuntime('Twig\Runtime\EscaperRuntime')->escape($this->env->getFunction('__')->getCallable()("This Week"), "html", null, true);
        yield "</span>
        </button>

        <div class='relative hidden xl:inline-flex h-8 px-4 text-xs/6  -ml-px items-center border border-gray-400 text-gray-600 bg-gray-100 font-medium'>
            <div id=\"indicator\" class=\"absolute left-0 top-0 w-full h-full htmx-indicator bg-stripe animate-bg-slide opacity-0 transition-opacity duration-300 delay-150\"></div>

            ";
        // line 182
        yield $this->env->getFunction('formatUsing')->getCallable()("dateRangeReadable", CoreExtension::getAttribute($this->env, $this->source, ($context["structure"] ?? null), "getStartDate", [], "any", false, false, false, 182), CoreExtension::getAttribute($this->env, $this->source, ($context["structure"] ?? null), "getEndDate", [], "any", false, false, false, 182));
        yield "
        </div>
            
        <button type='button' class='ttNav inline-flex items-center align-middle rounded-r h-8 px-3 text-xs/6 border border-gray-400 text-gray-600 bg-gray-100 font-medium hover:bg-gray-300 hover:text-gray-700 -ml-px'
            x-on:click='ttDate=\"";
        // line 186
        yield $this->env->getRuntime('Twig\Runtime\EscaperRuntime')->escape($this->extensions['Twig\Extension\CoreExtension']->formatDate(CoreExtension::getAttribute($this->env, $this->source, CoreExtension::getAttribute($this->env, $this->source, ($context["structure"] ?? null), "getCurrentDate", [], "any", false, false, false, 186), "modify", ["+1 week"], "method", false, false, false, 186), "Y-m-d"), "html", null, true);
        yield "\"'><span class='hidden sm:inline mr-1 sr-only'>";
        yield $this->env->getRuntime('Twig\Runtime\EscaperRuntime')->escape($this->env->getFunction('__')->getCallable()("Next Week"), "html", null, true);
        yield "</span>
        ";
        // line 187
        yield $this->env->getFunction('icon')->getCallable()("basic", "chevron-right", "inline-block size-5");
        yield "
        </button>

    </div>

    <div class=\" text-right inline-flex justify-end items-center text-xs/6\">

        <button type='button' class='ttNav inline-flex items-center rounded-l px-2 h-8 -mr-px text-base border border-gray-400 bg-gray-100 font-medium hover:bg-gray-300 text-gray-600 hover:text-gray-700' x-on:click='ttRefresh=true'>
            ";
        // line 195
        yield $this->env->getFunction('icon')->getCallable()("basic", "refresh", "size-4");
        yield "
            </span>
        </button>

        <input name='ttDateChooser' id='ttDateChooser' aria-label='";
        // line 199
        yield $this->env->getRuntime('Twig\Runtime\EscaperRuntime')->escape($this->env->getFunction('__')->getCallable()("Choose Date"), "html", null, true);
        yield "' maxlength=10 value='";
        yield $this->env->getRuntime('Twig\Runtime\EscaperRuntime')->escape($this->extensions['Twig\Extension\CoreExtension']->formatDate(CoreExtension::getAttribute($this->env, $this->source, ($context["structure"] ?? null), "getCurrentDate", [], "any", false, false, false, 199), "Y-m-d"), "html", null, true);
        yield "' type='date' required class='inline-flex border rounded-r bg-gray-100 text-xs/6 h-8 font-sans w-10 md:w-36 px-2 md:px-3'> 
    </div>

    </nav>
</form>

";
        return; yield '';
    }

    /**
     * @codeCoverageIgnore
     */
    public function getTemplateName()
    {
        return "ui/timetableNav.twig.html";
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
        return array (  528 => 199,  521 => 195,  510 => 187,  504 => 186,  497 => 182,  488 => 176,  484 => 175,  478 => 174,  472 => 171,  468 => 170,  464 => 169,  447 => 155,  443 => 154,  433 => 153,  421 => 152,  418 => 151,  416 => 150,  411 => 147,  404 => 143,  400 => 142,  392 => 141,  386 => 138,  382 => 137,  373 => 136,  371 => 135,  362 => 129,  358 => 128,  354 => 126,  348 => 122,  339 => 116,  335 => 115,  325 => 114,  313 => 113,  310 => 112,  308 => 111,  305 => 110,  303 => 109,  300 => 108,  287 => 103,  281 => 102,  267 => 101,  255 => 100,  252 => 99,  250 => 98,  247 => 97,  243 => 96,  236 => 92,  232 => 91,  228 => 89,  226 => 88,  221 => 85,  214 => 81,  210 => 80,  206 => 79,  196 => 71,  194 => 70,  191 => 69,  178 => 64,  172 => 63,  160 => 62,  148 => 61,  145 => 60,  143 => 59,  140 => 58,  136 => 57,  129 => 53,  125 => 52,  121 => 51,  117 => 49,  112 => 46,  102 => 42,  93 => 40,  89 => 39,  86 => 38,  82 => 37,  75 => 33,  71 => 32,  67 => 30,  65 => 29,  52 => 19,  42 => 12,  38 => 10,);
    }

    public function getSourceContext()
    {
        return new Source("", "ui/timetableNav.twig.html", "/var/www/html/gibbon/resources/templates/ui/timetableNav.twig.html");
    }
}
