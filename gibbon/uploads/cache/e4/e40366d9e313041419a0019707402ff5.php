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

/* components/form.twig.html */
class __TwigTemplate_2a29862783e7846137dff275de6fa4d0 extends Template
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
";
        // line 11
        $context["standardLayout"] = ((!CoreExtension::inFilter("noIntBorder", CoreExtension::getAttribute($this->env, $this->source, ($context["form"] ?? null), "getClass", [], "any", false, false, false, 11)) && !CoreExtension::inFilter("form-small", CoreExtension::getAttribute($this->env, $this->source, ($context["form"] ?? null), "getClass", [], "any", false, false, false, 11))) && !CoreExtension::inFilter("blank", CoreExtension::getAttribute($this->env, $this->source, ($context["form"] ?? null), "getClass", [], "any", false, false, false, 11)));
        // line 12
        $context["smallLayout"] = CoreExtension::inFilter("form-small", CoreExtension::getAttribute($this->env, $this->source, ($context["form"] ?? null), "getClass", [], "any", false, false, false, 12));
        // line 13
        $context["useSections"] = (!CoreExtension::inFilter("noIntBorder", CoreExtension::getAttribute($this->env, $this->source, ($context["form"] ?? null), "getClass", [], "any", false, false, false, 13)) && !CoreExtension::inFilter("blank", CoreExtension::getAttribute($this->env, $this->source, ($context["form"] ?? null), "getClass", [], "any", false, false, false, 13)));
        // line 14
        $context["useSaveWarning"] = (!CoreExtension::inFilter("noIntBorder", CoreExtension::getAttribute($this->env, $this->source, ($context["form"] ?? null), "getClass", [], "any", false, false, false, 14)) && !CoreExtension::inFilter("disable-warnings", CoreExtension::getAttribute($this->env, $this->source, ($context["form"] ?? null), "getClass", [], "any", false, false, false, 14)));
        // line 15
        yield "
";
        // line 16
        if ((($context["quickSave"] ?? null) && CoreExtension::getAttribute($this->env, $this->source, ($context["form"] ?? null), "getAction", [], "any", false, false, false, 16))) {
            // line 17
            yield "    <form x-validate ";
            yield CoreExtension::getAttribute($this->env, $this->source, ($context["form"] ?? null), "getAttributeString", [], "any", false, false, false, 17);
            yield " hx-trigger=\"quicksave, keydown[metaKey&&key=='s'] from:body\" hx-post=\"";
            yield $this->env->getRuntime('Twig\Runtime\EscaperRuntime')->escape(CoreExtension::getAttribute($this->env, $this->source, ($context["form"] ?? null), "getAction", [], "any", false, false, false, 17), "html", null, true);
            yield "\" hx-target=\".formStatusReturn\" hx-select=\"#alerts\" hx-swap=\"innerHTML show:none swap:0.5s\" hx-disinherit=\"*\" hx-vals='{\"HX-QuickSave\": true}' x-data=\"{'show': true, 'saving': false, 'invalid': false, 'submitting': false, 'showTimeout': false}\" x-on:htmx:before-request=\"if (\$event.detail.requestConfig.elt.nodeName == 'FORM') { saving = true; show = true;  clearTimeout(showTimeout); }\" x-on:htmx:after-swap=\"saving = false\" x-on:htmx:after-settle=\"showTimeout = setTimeout(() => show = false, 5000); \$dispatch('saved');\" x-ref=\"form\" @submit=\"\$validate.submit; invalid = !\$validate.isComplete(\$el); if (invalid) submitting = false;\" @change.debounce.750ms=\"if (invalid) invalid = !\$validate.isComplete(\$el); \">

    <div class=\"formStatus fixed bottom-0 right-4 z-50\" x-cloak>
        <div class=\"formIndicator magic drop-shadow-md\" x-show=\"saving\" >";
            // line 20
            yield $this->env->getRuntime('Twig\Runtime\EscaperRuntime')->escape($this->env->getFunction('__')->getCallable()("Saving"), "html", null, true);
            yield " ...</div>
        <div class=\"formStatusReturn drop-shadow-md\" x-show=\"!saving && show\" ></div>
    </div>
";
        } elseif ((CoreExtension::getAttribute($this->env, $this->source,         // line 23
($context["form"] ?? null), "getAction", [], "any", false, false, false, 23) != "ajax")) {
            // line 24
            yield "    <form x-validate ";
            yield CoreExtension::getAttribute($this->env, $this->source, ($context["form"] ?? null), "getAttributeString", [], "any", false, false, false, 24);
            yield " x-data=\"{'advancedOptions': ";
            yield $this->env->getRuntime('Twig\Runtime\EscaperRuntime')->escape(((CoreExtension::getAttribute($this->env, $this->source, ($context["form"] ?? null), "getData", ["advanced-options"], "method", true, true, false, 24)) ? (Twig\Extension\CoreExtension::default(CoreExtension::getAttribute($this->env, $this->source, ($context["form"] ?? null), "getData", ["advanced-options"], "method", false, false, false, 24), "false")) : ("false")), "html", null, true);
            yield ", 'invalid': false, 'submitting': false}\"  x-ref=\"form\" @submit=\"\$validate.submit; invalid = !\$validate.isComplete(\$el); if (invalid) submitting = false;\" @change.debounce.750ms=\"if (invalid) invalid = !\$validate.isComplete(\$el); \">
";
        }
        // line 26
        yield "
    ";
        // line 27
        if (CoreExtension::getAttribute($this->env, $this->source, ($context["form"] ?? null), "hasPages", [], "any", false, false, false, 27)) {
            // line 28
            yield "        <ul class=\"multiPartForm my-6\">
            ";
            // line 29
            $context['_parent'] = $context;
            $context['_seq'] = CoreExtension::ensureTraversable(CoreExtension::getAttribute($this->env, $this->source, ($context["form"] ?? null), "getPages", [], "any", false, false, false, 29));
            foreach ($context['_seq'] as $context["_key"] => $context["page"]) {
                // line 30
                yield "            <li class=\"step ";
                yield (((CoreExtension::getAttribute($this->env, $this->source, $context["page"], "number", [], "any", false, false, false, 30) <= CoreExtension::getAttribute($this->env, $this->source, ($context["form"] ?? null), "getCurrentPage", [], "any", false, false, false, 30))) ? ("active") : (""));
                yield "\">
                ";
                // line 31
                if (((CoreExtension::getAttribute($this->env, $this->source, $context["page"], "url", [], "any", false, false, false, 31) && (CoreExtension::getAttribute($this->env, $this->source, $context["page"], "number", [], "any", false, false, false, 31) <= CoreExtension::getAttribute($this->env, $this->source, ($context["form"] ?? null), "getMaxPage", [], "any", false, false, false, 31))) && (CoreExtension::getAttribute($this->env, $this->source, $context["page"], "number", [], "any", false, false, false, 31) != CoreExtension::getAttribute($this->env, $this->source, ($context["form"] ?? null), "getCurrentPage", [], "any", false, false, false, 31)))) {
                    // line 32
                    yield "                    <a href=\"";
                    yield $this->env->getRuntime('Twig\Runtime\EscaperRuntime')->escape(CoreExtension::getAttribute($this->env, $this->source, $context["page"], "url", [], "any", false, false, false, 32), "html", null, true);
                    yield "\" class=\"-mt-10 pt-10 text-gray-800 hover:text-purple-600 hover:underline\">
                ";
                }
                // line 34
                yield $this->env->getRuntime('Twig\Runtime\EscaperRuntime')->escape(CoreExtension::getAttribute($this->env, $this->source, $context["page"], "name", [], "any", false, false, false, 34), "html", null, true);
                // line 35
                if (((CoreExtension::getAttribute($this->env, $this->source, $context["page"], "url", [], "any", false, false, false, 35) && (CoreExtension::getAttribute($this->env, $this->source, $context["page"], "number", [], "any", false, false, false, 35) <= CoreExtension::getAttribute($this->env, $this->source, ($context["form"] ?? null), "getMaxPage", [], "any", false, false, false, 35))) && (CoreExtension::getAttribute($this->env, $this->source, $context["page"], "number", [], "any", false, false, false, 35) != CoreExtension::getAttribute($this->env, $this->source, ($context["form"] ?? null), "getCurrentPage", [], "any", false, false, false, 35)))) {
                    // line 36
                    yield "                    </a>
                ";
                }
                // line 38
                yield "            </li>
            ";
            }
            $_parent = $context['_parent'];
            unset($context['_seq'], $context['_iterated'], $context['_key'], $context['page'], $context['_parent'], $context['loop']);
            $context = array_intersect_key($context, $_parent) + $_parent;
            // line 40
            yield "        </ul>
    ";
        }
        // line 42
        yield "
    ";
        // line 43
        if ((CoreExtension::getAttribute($this->env, $this->source, ($context["form"] ?? null), "getTitle", [], "any", false, false, false, 43) &&  !CoreExtension::getAttribute($this->env, $this->source, ($context["form"] ?? null), "getHeader", [], "any", false, false, false, 43))) {
            // line 44
            yield "        <h2>";
            yield $this->env->getRuntime('Twig\Runtime\EscaperRuntime')->escape(CoreExtension::getAttribute($this->env, $this->source, ($context["form"] ?? null), "getTitle", [], "any", false, false, false, 44), "html", null, true);
            yield "</h2>
    ";
        }
        // line 46
        yield "
    ";
        // line 47
        if (CoreExtension::getAttribute($this->env, $this->source, ($context["form"] ?? null), "getDescription", [], "any", false, false, false, 47)) {
            // line 48
            yield "        <p>";
            yield CoreExtension::getAttribute($this->env, $this->source, ($context["form"] ?? null), "getDescription", [], "any", false, false, false, 48);
            yield "</p>
    ";
        }
        // line 50
        yield "
    ";
        // line 51
        if (($context["introduction"] ?? null)) {
            // line 52
            yield "        <p>";
            yield ($context["introduction"] ?? null);
            yield "</p>
    ";
        }
        // line 54
        yield "
    ";
        // line 55
        yield from $this->unwrap()->yieldBlock('header', $context, $blocks);
        // line 67
        yield "
    ";
        // line 68
        $context['_parent'] = $context;
        $context['_seq'] = CoreExtension::ensureTraversable(CoreExtension::getAttribute($this->env, $this->source, ($context["form"] ?? null), "getHiddenValues", [], "any", false, false, false, 68));
        foreach ($context['_seq'] as $context["_key"] => $context["values"]) {
            // line 69
            yield "        <input type=\"hidden\" name=\"";
            yield $this->env->getRuntime('Twig\Runtime\EscaperRuntime')->escape(CoreExtension::getAttribute($this->env, $this->source, $context["values"], "name", [], "any", false, false, false, 69), "html", null, true);
            yield "\" value=\"";
            yield $this->env->getRuntime('Twig\Runtime\EscaperRuntime')->escape(CoreExtension::getAttribute($this->env, $this->source, $context["values"], "value", [], "any", false, false, false, 69), "html", null, true);
            yield "\">
    ";
        }
        $_parent = $context['_parent'];
        unset($context['_seq'], $context['_iterated'], $context['_key'], $context['values'], $context['_parent'], $context['loop']);
        $context = array_intersect_key($context, $_parent) + $_parent;
        // line 71
        yield "
    ";
        // line 72
        if ((CoreExtension::getAttribute($this->env, $this->source, ($context["form"] ?? null), "getSectionCount", [], "any", false, false, false, 72) > 0)) {
            // line 73
            yield "    <div id=\"";
            yield $this->env->getRuntime('Twig\Runtime\EscaperRuntime')->escape(CoreExtension::getAttribute($this->env, $this->source, ($context["form"] ?? null), "getID", [], "any", false, false, false, 73), "html", null, true);
            yield "Wrap\" class=\"w-full grid grid-cols-5 xl:grid-cols-8 gap-2 sm:gap-4 font-sans text-xs text-gray-700  ";
            yield ((CoreExtension::inFilter("noIntBorder", CoreExtension::getAttribute($this->env, $this->source, ($context["form"] ?? null), "getClass", [], "any", false, false, false, 73))) ? ("border bg-blue-50 rounded p-4") : (""));
            yield "\" style=\"\" cellspacing=\"0\" 
        ";
            // line 74
            if (($context["useSaveWarning"] ?? null)) {
                // line 75
                yield "        x-data=\"{changed: false, 
            checkInput(formElement) {  
                document.getElementById(formElement.id).addEventListener('input', (evt) => {
                    this.changed = true;
                    window.onbeforeunload = function(event) {
                        if (event.target.activeElement.nodeName == 'INPUT' || event.target.activeElement.type=='submit' || event.target.activeElement.classList.contains('submit-button')) return;
                        return Gibbon.config.htmx.unload_confirm;
                    };
                });
            },
            afterSave() {
                this.changed = false;
                window.onbeforeunload = null;
            },
        }\" x-init=\"checkInput(\$el)\" @saved.window=\"afterSave()\"
        ";
            }
            // line 91
            yield "        >

        <div class=\"";
            // line 93
            yield (((($context["standardLayout"] ?? null) && CoreExtension::getAttribute($this->env, $this->source, ($context["form"] ?? null), "hasMeta", [], "any", false, false, false, 93))) ? ("col-span-6") : ("col-span-8"));
            yield "\">

        ";
            // line 95
            $context['_parent'] = $context;
            $context['_seq'] = CoreExtension::ensureTraversable(CoreExtension::getAttribute($this->env, $this->source, ($context["form"] ?? null), "getSections", [], "any", false, false, false, 95));
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
            foreach ($context['_seq'] as $context["_key"] => $context["section"]) {
                // line 96
                yield "            ";
                $context["rows"] = CoreExtension::getAttribute($this->env, $this->source, $context["section"], "getRows", [], "any", false, false, false, 96);
                // line 97
                yield "            ";
                $context["sectionLoop"] = $context["loop"];
                // line 98
                yield "
            ";
                // line 99
                if (($context["useSections"] ?? null)) {
                    // line 100
                    yield "            <section id=\"";
                    yield $this->env->getRuntime('Twig\Runtime\EscaperRuntime')->escape(CoreExtension::getAttribute($this->env, $this->source, $context["section"], "getID", [], "any", false, false, false, 100), "html", null, true);
                    yield "\" x-data=\"{sectionOpen: ";
                    yield $this->env->getRuntime('Twig\Runtime\EscaperRuntime')->escape(((CoreExtension::getAttribute($this->env, $this->source, $context["section"], "getOpen", [], "any", true, true, false, 100)) ? (Twig\Extension\CoreExtension::default(CoreExtension::getAttribute($this->env, $this->source, $context["section"], "getOpen", [], "any", false, false, false, 100), 0)) : (0)), "html", null, true);
                    yield " }\" class=\"relative ";
                    yield $this->env->getRuntime('Twig\Runtime\EscaperRuntime')->escape(Twig\Extension\CoreExtension::first($this->env->getCharset(), Twig\Extension\CoreExtension::split($this->env->getCharset(), CoreExtension::getAttribute($this->env, $this->source, Twig\Extension\CoreExtension::first($this->env->getCharset(), ($context["rows"] ?? null)), "class", [], "any", false, false, false, 100), " ")), "html", null, true);
                    yield " mb-6 rounded bg-gray-50 border  ";
                    yield (( !CoreExtension::getAttribute($this->env, $this->source, ($context["sectionLoop"] ?? null), "last", [], "any", false, false, false, 100)) ? ("pb-4 border-t-4 focus-within:border-t-blue-500 transition") : (""));
                    yield " ";
                    yield (((CoreExtension::getAttribute($this->env, $this->source, $context["section"], "getID", [], "any", false, false, false, 100) == "submit")) ? ("w-full sm:sticky -bottom-px -mt-px mb-px z-40") : (((($context["standardLayout"] ?? null)) ? ("  ") : (""))));
                    yield "\" >

                ";
                    // line 102
                    if ( !CoreExtension::getAttribute($this->env, $this->source, $context["section"], "getOpen", [], "any", false, false, false, 102)) {
                        // line 103
                        yield "                <div @click=\"sectionOpen = !sectionOpen\" class=\"text-gray-500 hover:text-blue-500\">
                    <div class=\"absolute top-0 right-0 mt-5 mr-6\">
                        <div x-show=\"!sectionOpen\" class=\"p-2\">
                            ";
                        // line 106
                        yield $this->env->getFunction('icon')->getCallable()("solid", "add", "size-6 text-inherit");
                        yield "
                        </div>
                        <div x-cloak x-show=\"sectionOpen\" class=\"p-2\">
                            ";
                        // line 109
                        yield $this->env->getFunction('icon')->getCallable()("solid", "cross", "size-6");
                        yield "
                        </div>
                    </div>

                    <h3 ";
                        // line 113
                        yield ((CoreExtension::getAttribute($this->env, $this->source, $context["section"], "getOpen", [], "any", false, false, false, 113)) ? ("x-cloak") : (""));
                        yield " x-show=\"!sectionOpen\" class=\"px-4 sm:px-8 pt-6 pb-2 font-semibold text-gray-950 text-2xl/8 sm:text-xl/8\">
                        ";
                        // line 114
                        yield $this->env->getRuntime('Twig\Runtime\EscaperRuntime')->escape(CoreExtension::getAttribute($this->env, $this->source, $context["section"], "getHeading", [], "any", false, false, false, 114), "html", null, true);
                        yield "
                    </h3>

                </div>
                ";
                    }
                    // line 119
                    yield "               
            ";
                }
                // line 121
                yield "
            ";
                // line 122
                $context["isformTable"] = CoreExtension::getAttribute($this->env, $this->source, Twig\Extension\CoreExtension::first($this->env->getCharset(), CoreExtension::getAttribute($this->env, $this->source, Twig\Extension\CoreExtension::first($this->env->getCharset(), ($context["rows"] ?? null)), "getElements", [], "any", false, false, false, 122)), "isInstanceOf", ["Gibbon\\Forms\\Layout\\Table"], "method", false, false, false, 122);
                // line 123
                yield "
            ";
                // line 124
                $context['_parent'] = $context;
                $context['_seq'] = CoreExtension::ensureTraversable(($context["rows"] ?? null));
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
                    // line 125
                    yield "
                ";
                    // line 126
                    $context["rowLoop"] = $context["loop"];
                    // line 127
                    yield "
                ";
                    // line 128
                    $context["rowClass"] = (((CoreExtension::getAttribute($this->env, $this->source, $context["section"], "getID", [], "any", false, false, false, 128) == "submit")) ? ("flex flex-row content-center p-0 gap-2 sm:gap-4 justify-end sm:items-center") : ("flex flex-col sm:flex-row  content-center p-0 gap-2 sm:gap-4 justify-between sm:items-start"));
                    // line 132
                    yield "                
                <div id=\"";
                    // line 133
                    yield $this->env->getRuntime('Twig\Runtime\EscaperRuntime')->escape(CoreExtension::getAttribute($this->env, $this->source, $context["row"], "getID", [], "any", false, false, false, 133), "html", null, true);
                    yield "\"  class=\"";
                    yield (((($context["standardLayout"] ?? null) &&  !($context["isformTable"] ?? null))) ? (" px-4 sm:px-8 py-4") : (((($context["smallLayout"] ?? null)) ? ("px-2 py-2") : (((CoreExtension::inFilter("noIntBorder", CoreExtension::getAttribute($this->env, $this->source, ($context["form"] ?? null), "getClass", [], "any", false, false, false, 133))) ? ("py-2") : (""))))));
                    yield "  ";
                    yield $this->env->getRuntime('Twig\Runtime\EscaperRuntime')->escape(Twig\Extension\CoreExtension::replace(CoreExtension::getAttribute($this->env, $this->source, $context["row"], "getClass", [], "any", false, false, false, 133), ["standardWidth" => ""]), "html", null, true);
                    yield " ";
                    yield $this->env->getRuntime('Twig\Runtime\EscaperRuntime')->escape(($context["rowClass"] ?? null), "html", null, true);
                    yield "  \" ";
                    yield CoreExtension::getAttribute($this->env, $this->source, $context["row"], "getAttributeString", ["", "id,class"], "method", false, false, false, 133);
                    yield "
                
                ";
                    // line 135
                    if ( !CoreExtension::getAttribute($this->env, $this->source, $context["section"], "getOpen", [], "any", false, false, false, 135)) {
                        yield "x-show=\"sectionOpen\" x-cloak ";
                    }
                    // line 136
                    yield "                
                >
  
                ";
                    // line 139
                    if ((($context["quickSave"] ?? null) && (CoreExtension::getAttribute($this->env, $this->source, $context["section"], "getID", [], "any", false, false, false, 139) == "submit"))) {
                        // line 140
                        yield "                    <span class=\"text-xs text-gray-600 flex-1\">
                        ";
                        // line 141
                        yield $this->env->getFunction('__')->getCallable()("Press {shortcut} to {action}", ["shortcut" => "<kbd class=\"bg-white\">⌘ Cmd</kbd> + <kbd class=\"bg-white\">S</kbd>", "action" => $this->env->getFunction('__')->getCallable()("quick save")]);
                        yield "
                    </span>
                ";
                    }
                    // line 144
                    yield "
                ";
                    // line 145
                    if (CoreExtension::inFilter("draggableRow", CoreExtension::getAttribute($this->env, $this->source, $context["row"], "getClass", [], "any", false, false, false, 145))) {
                        // line 146
                        yield "                    <div class=\"drag-handle w-2 h-6 -ml-4 px-px border-4 border-dotted cursor-move\"></div>
                ";
                    }
                    // line 148
                    yield "        
                ";
                    // line 149
                    $context['_parent'] = $context;
                    $context['_seq'] = CoreExtension::ensureTraversable(CoreExtension::getAttribute($this->env, $this->source, $context["row"], "getElements", [], "any", false, false, false, 149));
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
                        // line 150
                        yield "
                    ";
                        // line 151
                        if (CoreExtension::getAttribute($this->env, $this->source, $context["element"], "isInstanceOf", ["Gibbon\\Forms\\Layout\\Heading"], "method", false, false, false, 151)) {
                            // line 152
                            yield "                        ";
                            $context["class"] = "flex-grow justify-center";
                            // line 153
                            yield "                    ";
                        } elseif (CoreExtension::getAttribute($this->env, $this->source, $context["element"], "isInstanceOf", ["Gibbon\\Forms\\Layout\\Label"], "method", false, false, false, 153)) {
                            // line 154
                            yield "                        ";
                            $context["class"] = "sm:w-2/5 flex flex-col justify-center sm:mb-0";
                            // line 155
                            yield "                    ";
                        } elseif (CoreExtension::getAttribute($this->env, $this->source, $context["element"], "isInstanceOf", ["Gibbon\\Forms\\Layout\\Column"], "method", false, false, false, 155)) {
                            // line 156
                            yield "                        ";
                            $context["class"] = (((CoreExtension::getAttribute($this->env, $this->source, $context["loop"], "last", [], "any", false, false, false, 156) && (CoreExtension::getAttribute($this->env, $this->source, $context["loop"], "length", [], "any", false, false, false, 156) == 2))) ? ("flex-1 relative flex justify-end items-center") : (""));
                            // line 157
                            yield "                    ";
                        } elseif ((CoreExtension::getAttribute($this->env, $this->source, $context["loop"], "last", [], "any", false, false, false, 157) && ((CoreExtension::getAttribute($this->env, $this->source, $context["loop"], "length", [], "any", false, false, false, 157) == 2) || CoreExtension::inFilter("noIntBorder", CoreExtension::getAttribute($this->env, $this->source, ($context["form"] ?? null), "getClass", [], "any", false, false, false, 157))))) {
                            // line 158
                            yield "                        ";
                            $context["class"] = "flex-1 relative flex justify-end items-center";
                            // line 159
                            yield "                    ";
                        } else {
                            // line 160
                            yield "                        ";
                            $context["class"] = "";
                            // line 161
                            yield "                    ";
                        }
                        // line 162
                        yield "
                    ";
                        // line 163
                        $context["hasClass"] = (CoreExtension::getAttribute($this->env, $this->source, $context["element"], "instanceOf", ["Gibbon\\Forms\\Layout\\Element"], "method", false, false, false, 163) || CoreExtension::getAttribute($this->env, $this->source, $context["element"], "instanceOf", ["Gibbon\\Forms\\Layout\\Column"], "method", false, false, false, 163));
                        // line 164
                        yield "                    ";
                        $context["class"] = ((($context["hasClass"] ?? null)) ? (((($context["class"] ?? null) . " ") . CoreExtension::getAttribute($this->env, $this->source, $context["element"], "getClass", [], "any", false, false, false, 164))) : (($context["class"] ?? null)));
                        // line 165
                        yield "                    <div class=\"";
                        yield $this->env->getRuntime('Twig\Runtime\EscaperRuntime')->escape(($context["class"] ?? null), "html", null, true);
                        yield " ";
                        (((!CoreExtension::inFilter("flex-", ($context["class"] ?? null)) && ($context["section"] != "submit"))) ? (yield $this->env->getRuntime('Twig\Runtime\EscaperRuntime')->escape(((CoreExtension::getAttribute($this->env, $this->source, $context["element"], "getOuterClass", [], "any", true, true, false, 165)) ? (Twig\Extension\CoreExtension::default(CoreExtension::getAttribute($this->env, $this->source, $context["element"], "getOuterClass", [], "any", false, false, false, 165), "flex-1")) : ("flex-1")), "html", null, true)) : (yield ""));
                        yield "\" ";
                        yield CoreExtension::getAttribute($this->env, $this->source, $context["element"], "getAttributeString", ["x-show"], "method", false, false, false, 165);
                        yield ">

                        ";
                        // line 167
                        if ((($context["useSaveWarning"] ?? null) && (CoreExtension::getAttribute($this->env, $this->source, $context["element"], "getAttribute", ["id"], "method", false, false, false, 167) == "Submit"))) {
                            // line 168
                            yield "                            <span x-cloak x-show=\"changed\" class=\"tag message mr-4 whitespace-nowrap\">";
                            yield $this->env->getRuntime('Twig\Runtime\EscaperRuntime')->escape($this->env->getFunction('__')->getCallable()("Unsaved Changes"), "html", null, true);
                            yield "</span>
                        ";
                        }
                        // line 170
                        yield "
                        ";
                        // line 171
                        if ((CoreExtension::getAttribute($this->env, $this->source, $context["element"], "getAttribute", ["id"], "method", false, false, false, 171) == "Submit")) {
                            // line 172
                            yield "                            <span x-cloak x-show=\"invalid\" class=\"tag error mr-4 whitespace-nowrap\">";
                            yield $this->env->getRuntime('Twig\Runtime\EscaperRuntime')->escape($this->env->getFunction('__')->getCallable()("Please Check Form"), "html", null, true);
                            yield "</span>
                        ";
                        }
                        // line 174
                        yield "
                        ";
                        // line 175
                        yield CoreExtension::getAttribute($this->env, $this->source, $context["element"], "getOutput", [], "any", false, false, false, 175);
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
                    // line 178
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
                // line 181
                yield "
            ";
                // line 182
                if (($context["useSections"] ?? null)) {
                    // line 183
                    yield "            </section>
            ";
                }
                // line 185
                yield "
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
            unset($context['_seq'], $context['_iterated'], $context['_key'], $context['section'], $context['_parent'], $context['loop']);
            $context = array_intersect_key($context, $_parent) + $_parent;
            // line 187
            yield "        </div>

        ";
            // line 189
            if ((CoreExtension::getAttribute($this->env, $this->source, ($context["form"] ?? null), "hasMeta", [], "any", false, false, false, 189) && (CoreExtension::getAttribute($this->env, $this->source, CoreExtension::getAttribute($this->env, $this->source, ($context["form"] ?? null), "getMeta", [], "any", false, false, false, 189), "getElementCount", [], "any", false, false, false, 189) > 0))) {
                // line 190
                yield "        <aside class=\"hidden xl:flex flex-col col-span-2 h-min bg-gray-50 rounded border border-gray-400 border-t-4 border-t-gray-400\">
            ";
                // line 191
                $context['_parent'] = $context;
                $context['_seq'] = CoreExtension::ensureTraversable(CoreExtension::getAttribute($this->env, $this->source, CoreExtension::getAttribute($this->env, $this->source, ($context["form"] ?? null), "getMeta", [], "any", false, false, false, 191), "getElements", [], "any", false, false, false, 191));
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
                    // line 192
                    yield "                ";
                    $context["hasClass"] = (CoreExtension::getAttribute($this->env, $this->source, $context["element"], "instanceOf", ["Gibbon\\Forms\\Layout\\Element"], "method", false, false, false, 192) || CoreExtension::getAttribute($this->env, $this->source, $context["element"], "instanceOf", ["Gibbon\\Forms\\Layout\\Row"], "method", false, false, false, 192));
                    // line 193
                    yield "            
                <div class=\"p-4 ";
                    // line 194
                    yield (( !CoreExtension::getAttribute($this->env, $this->source, $context["loop"], "last", [], "any", false, false, false, 194)) ? ("border-b") : (""));
                    yield " ";
                    ((($context["hasClass"] ?? null)) ? (yield $this->env->getRuntime('Twig\Runtime\EscaperRuntime')->escape(CoreExtension::getAttribute($this->env, $this->source, $context["element"], "getClass", [], "any", false, false, false, 194), "html", null, true)) : (yield ""));
                    yield "\" 
                    ";
                    // line 195
                    yield CoreExtension::getAttribute($this->env, $this->source, $context["element"], "getAttributeString", ["", "id,class"], "method", false, false, false, 195);
                    yield ">
                    ";
                    // line 196
                    yield CoreExtension::getAttribute($this->env, $this->source, $context["element"], "getOutput", [], "any", false, false, false, 196);
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
                // line 199
                yield "
            <template x-if=\"invalid\">
                <div class=\"p-4 border-t\">
                    <span class=\"tag error\">";
                // line 202
                yield $this->env->getRuntime('Twig\Runtime\EscaperRuntime')->escape($this->env->getFunction('__')->getCallable()("Please check these fields"), "html", null, true);
                yield ":</span>

                    <ul class=\"ml-6\">
                    <template x-for=\"v in \$validate.data(\$refs.form)\" >
                        <template x-if=\"!v.valid && v.node.labels.length > 0\">
                        <li class=\"py-0.5\">
                            <a x-bind:href=\"'#' + v.name\" target=\"_self\" x-text=\"v.node.labels[0].ariaLabel\" :aria-label=\"v.name\" class=\"text-sm hover:underline\"></a>
                        </li>
                        </template>
                    </template>
                    

                    </ul>
                </div>
            </template>
        </aside>
        ";
            }
            // line 219
            yield "
    </div>
    ";
        }
        // line 222
        yield "
    ";
        // line 223
        if (($context["postScript"] ?? null)) {
            // line 224
            yield "    <p>";
            yield ($context["postScript"] ?? null);
            yield "</p>
    ";
        }
        // line 226
        yield "
    <script type=\"text/javascript\">
        ";
        // line 228
        if ((($context["quickSave"] ?? null) && CoreExtension::getAttribute($this->env, $this->source, ($context["form"] ?? null), "getAction", [], "any", false, false, false, 228))) {
            // line 229
            yield "        \$(document).keydown(function(event) {
            if (!((String.fromCharCode(event.which).toLowerCase() == 's' || event.keyCode == 13) && event.metaKey) && !(event.which == 19)) return true;
            event.preventDefault();
            return false;
        });
        ";
        }
        // line 235
        yield "
        ";
        // line 236
        $context['_parent'] = $context;
        $context['_seq'] = CoreExtension::ensureTraversable(($context["javascript"] ?? null));
        foreach ($context['_seq'] as $context["_key"] => $context["code"]) {
            // line 237
            yield "            ";
            yield $context["code"];
            yield "
        ";
        }
        $_parent = $context['_parent'];
        unset($context['_seq'], $context['_iterated'], $context['_key'], $context['code'], $context['_parent'], $context['loop']);
        $context = array_intersect_key($context, $_parent) + $_parent;
        // line 239
        yield "    </script>

";
        // line 241
        if ((CoreExtension::getAttribute($this->env, $this->source, ($context["form"] ?? null), "getAction", [], "any", false, false, false, 241) != "ajax")) {
            // line 242
            yield "</form>
";
        }
        return; yield '';
    }

    // line 55
    public function block_header($context, array $blocks = [])
    {
        $macros = $this->macros;
        // line 56
        yield "        <header class=\"relative print:hidden flex justify-between items-end mb-2 ";
        yield ((($context["standardLayout"] ?? null)) ? ("") : (""));
        yield "\">
            ";
        // line 57
        if (CoreExtension::getAttribute($this->env, $this->source, ($context["form"] ?? null), "getHeader", [], "any", false, false, false, 57)) {
            // line 58
            yield "                <h2>";
            yield $this->env->getRuntime('Twig\Runtime\EscaperRuntime')->escape(CoreExtension::getAttribute($this->env, $this->source, ($context["form"] ?? null), "getTitle", [], "any", false, false, false, 58), "html", null, true);
            yield "</h2>
                <div class=\"linkTop flex justify-end gap-2 h-10 py-px\">
                    ";
            // line 60
            $context['_parent'] = $context;
            $context['_seq'] = CoreExtension::ensureTraversable(CoreExtension::getAttribute($this->env, $this->source, ($context["form"] ?? null), "getHeader", [], "any", false, false, false, 60));
            foreach ($context['_seq'] as $context["_key"] => $context["action"]) {
                // line 61
                yield "                        ";
                yield CoreExtension::getAttribute($this->env, $this->source, $context["action"], "getOutput", [], "any", false, false, false, 61);
                yield "
                    ";
            }
            $_parent = $context['_parent'];
            unset($context['_seq'], $context['_iterated'], $context['_key'], $context['action'], $context['_parent'], $context['loop']);
            $context = array_intersect_key($context, $_parent) + $_parent;
            // line 63
            yield "                </div>
            ";
        }
        // line 65
        yield "        </header>
    ";
        return; yield '';
    }

    /**
     * @codeCoverageIgnore
     */
    public function getTemplateName()
    {
        return "components/form.twig.html";
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
        return array (  708 => 65,  704 => 63,  695 => 61,  691 => 60,  685 => 58,  683 => 57,  678 => 56,  674 => 55,  667 => 242,  665 => 241,  661 => 239,  652 => 237,  648 => 236,  645 => 235,  637 => 229,  635 => 228,  631 => 226,  625 => 224,  623 => 223,  620 => 222,  615 => 219,  595 => 202,  590 => 199,  573 => 196,  569 => 195,  563 => 194,  560 => 193,  557 => 192,  540 => 191,  537 => 190,  535 => 189,  531 => 187,  516 => 185,  512 => 183,  510 => 182,  507 => 181,  491 => 178,  474 => 175,  471 => 174,  465 => 172,  463 => 171,  460 => 170,  454 => 168,  452 => 167,  442 => 165,  439 => 164,  437 => 163,  434 => 162,  431 => 161,  428 => 160,  425 => 159,  422 => 158,  419 => 157,  416 => 156,  413 => 155,  410 => 154,  407 => 153,  404 => 152,  402 => 151,  399 => 150,  382 => 149,  379 => 148,  375 => 146,  373 => 145,  370 => 144,  364 => 141,  361 => 140,  359 => 139,  354 => 136,  350 => 135,  337 => 133,  334 => 132,  332 => 128,  329 => 127,  327 => 126,  324 => 125,  307 => 124,  304 => 123,  302 => 122,  299 => 121,  295 => 119,  287 => 114,  283 => 113,  276 => 109,  270 => 106,  265 => 103,  263 => 102,  249 => 100,  247 => 99,  244 => 98,  241 => 97,  238 => 96,  221 => 95,  216 => 93,  212 => 91,  194 => 75,  192 => 74,  185 => 73,  183 => 72,  180 => 71,  169 => 69,  165 => 68,  162 => 67,  160 => 55,  157 => 54,  151 => 52,  149 => 51,  146 => 50,  140 => 48,  138 => 47,  135 => 46,  129 => 44,  127 => 43,  124 => 42,  120 => 40,  113 => 38,  109 => 36,  107 => 35,  105 => 34,  99 => 32,  97 => 31,  92 => 30,  88 => 29,  85 => 28,  83 => 27,  80 => 26,  72 => 24,  70 => 23,  64 => 20,  55 => 17,  53 => 16,  50 => 15,  48 => 14,  46 => 13,  44 => 12,  42 => 11,  39 => 10,);
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

{% set standardLayout = \"noIntBorder\" not in form.getClass and \"form-small\" not in form.getClass and \"blank\" not in form.getClass %}
{% set smallLayout = \"form-small\" in form.getClass %}
{% set useSections = \"noIntBorder\" not in form.getClass and \"blank\" not in form.getClass %}
{% set useSaveWarning = \"noIntBorder\" not in form.getClass and \"disable-warnings\" not in form.getClass %}

{% if quickSave and form.getAction %}
    <form x-validate {{ form.getAttributeString|raw }} hx-trigger=\"quicksave, keydown[metaKey&&key=='s'] from:body\" hx-post=\"{{ form.getAction }}\" hx-target=\".formStatusReturn\" hx-select=\"#alerts\" hx-swap=\"innerHTML show:none swap:0.5s\" hx-disinherit=\"*\" hx-vals='{\"HX-QuickSave\": true}' x-data=\"{'show': true, 'saving': false, 'invalid': false, 'submitting': false, 'showTimeout': false}\" x-on:htmx:before-request=\"if (\$event.detail.requestConfig.elt.nodeName == 'FORM') { saving = true; show = true;  clearTimeout(showTimeout); }\" x-on:htmx:after-swap=\"saving = false\" x-on:htmx:after-settle=\"showTimeout = setTimeout(() => show = false, 5000); \$dispatch('saved');\" x-ref=\"form\" @submit=\"\$validate.submit; invalid = !\$validate.isComplete(\$el); if (invalid) submitting = false;\" @change.debounce.750ms=\"if (invalid) invalid = !\$validate.isComplete(\$el); \">

    <div class=\"formStatus fixed bottom-0 right-4 z-50\" x-cloak>
        <div class=\"formIndicator magic drop-shadow-md\" x-show=\"saving\" >{{ __('Saving') }} ...</div>
        <div class=\"formStatusReturn drop-shadow-md\" x-show=\"!saving && show\" ></div>
    </div>
{% elseif form.getAction != 'ajax' %}
    <form x-validate {{ form.getAttributeString|raw }} x-data=\"{'advancedOptions': {{ form.getData('advanced-options')|default('false') }}, 'invalid': false, 'submitting': false}\"  x-ref=\"form\" @submit=\"\$validate.submit; invalid = !\$validate.isComplete(\$el); if (invalid) submitting = false;\" @change.debounce.750ms=\"if (invalid) invalid = !\$validate.isComplete(\$el); \">
{% endif %}

    {% if form.hasPages %}
        <ul class=\"multiPartForm my-6\">
            {% for page in form.getPages %}
            <li class=\"step {{ page.number <= form.getCurrentPage ? 'active' : '' }}\">
                {% if page.url and page.number <= form.getMaxPage and page.number != form.getCurrentPage %}
                    <a href=\"{{ page.url }}\" class=\"-mt-10 pt-10 text-gray-800 hover:text-purple-600 hover:underline\">
                {% endif %}
                {{- page.name -}}
                {% if page.url and page.number <= form.getMaxPage and page.number != form.getCurrentPage %}
                    </a>
                {% endif %}
            </li>
            {% endfor %}
        </ul>
    {% endif %}

    {% if form.getTitle and not form.getHeader %}
        <h2>{{ form.getTitle }}</h2>
    {% endif %}

    {% if form.getDescription %}
        <p>{{ form.getDescription|raw }}</p>
    {% endif %}

    {% if introduction %}
        <p>{{ introduction|raw }}</p>
    {% endif %}

    {% block header %}
        <header class=\"relative print:hidden flex justify-between items-end mb-2 {{ standardLayout ? '' }}\">
            {% if form.getHeader %}
                <h2>{{ form.getTitle }}</h2>
                <div class=\"linkTop flex justify-end gap-2 h-10 py-px\">
                    {% for action in form.getHeader %}
                        {{ action.getOutput|raw }}
                    {% endfor %}
                </div>
            {% endif %}
        </header>
    {% endblock header %}

    {% for values in form.getHiddenValues %}
        <input type=\"hidden\" name=\"{{ values.name }}\" value=\"{{ values.value }}\">
    {% endfor %}

    {% if form.getSectionCount > 0 %}
    <div id=\"{{ form.getID }}Wrap\" class=\"w-full grid grid-cols-5 xl:grid-cols-8 gap-2 sm:gap-4 font-sans text-xs text-gray-700  {{ \"noIntBorder\" in form.getClass ? 'border bg-blue-50 rounded p-4' }}\" style=\"\" cellspacing=\"0\" 
        {% if useSaveWarning %}
        x-data=\"{changed: false, 
            checkInput(formElement) {  
                document.getElementById(formElement.id).addEventListener('input', (evt) => {
                    this.changed = true;
                    window.onbeforeunload = function(event) {
                        if (event.target.activeElement.nodeName == 'INPUT' || event.target.activeElement.type=='submit' || event.target.activeElement.classList.contains('submit-button')) return;
                        return Gibbon.config.htmx.unload_confirm;
                    };
                });
            },
            afterSave() {
                this.changed = false;
                window.onbeforeunload = null;
            },
        }\" x-init=\"checkInput(\$el)\" @saved.window=\"afterSave()\"
        {% endif %}
        >

        <div class=\"{{ standardLayout and form.hasMeta ? 'col-span-6' : 'col-span-8' }}\">

        {% for section in form.getSections %}
            {% set rows = section.getRows %}
            {% set sectionLoop = loop %}

            {% if useSections %}
            <section id=\"{{ section.getID }}\" x-data=\"{sectionOpen: {{ section.getOpen|default(0) }} }\" class=\"relative {{ (rows|first).class|split(' ')|first }} mb-6 rounded bg-gray-50 border  {{ not sectionLoop.last ? 'pb-4 border-t-4 focus-within:border-t-blue-500 transition' }} {{ section.getID == 'submit' ? 'w-full sm:sticky -bottom-px -mt-px mb-px z-40' : standardLayout ? '  ' : ''}}\" >

                {% if not section.getOpen %}
                <div @click=\"sectionOpen = !sectionOpen\" class=\"text-gray-500 hover:text-blue-500\">
                    <div class=\"absolute top-0 right-0 mt-5 mr-6\">
                        <div x-show=\"!sectionOpen\" class=\"p-2\">
                            {{ icon('solid', 'add', 'size-6 text-inherit')}}
                        </div>
                        <div x-cloak x-show=\"sectionOpen\" class=\"p-2\">
                            {{ icon('solid', 'cross', 'size-6')}}
                        </div>
                    </div>

                    <h3 {{ section.getOpen ? 'x-cloak' }} x-show=\"!sectionOpen\" class=\"px-4 sm:px-8 pt-6 pb-2 font-semibold text-gray-950 text-2xl/8 sm:text-xl/8\">
                        {{ section.getHeading }}
                    </h3>

                </div>
                {% endif %}
               
            {% endif %}

            {% set isformTable = ((rows|first).getElements|first).isInstanceOf('Gibbon\\\\Forms\\\\Layout\\\\Table') %}

            {% for num, row in rows %}

                {% set rowLoop = loop %}

                {% set rowClass = section.getID == 'submit' 
                    ? 'flex flex-row content-center p-0 gap-2 sm:gap-4 justify-end sm:items-center' 
                    : 'flex flex-col sm:flex-row  content-center p-0 gap-2 sm:gap-4 justify-between sm:items-start' 
                %}
                
                <div id=\"{{ row.getID }}\"  class=\"{{ standardLayout and not isformTable ? ' px-4 sm:px-8 py-4' : smallLayout ? 'px-2 py-2' : \"noIntBorder\" in form.getClass ? 'py-2' }}  {{ row.getClass|replace({'standardWidth': ''}) }} {{ rowClass }}  \" {{ row.getAttributeString('', 'id,class')|raw }}
                
                {% if not section.getOpen %}x-show=\"sectionOpen\" x-cloak {% endif %}
                
                >
  
                {% if quickSave and section.getID == 'submit' %}
                    <span class=\"text-xs text-gray-600 flex-1\">
                        {{ __('Press {shortcut} to {action}', {shortcut: '<kbd class=\"bg-white\">⌘ Cmd</kbd> + <kbd class=\"bg-white\">S</kbd>', action: __('quick save')} )|raw }}
                    </span>
                {% endif %}

                {% if \"draggableRow\" in row.getClass %}
                    <div class=\"drag-handle w-2 h-6 -ml-4 px-px border-4 border-dotted cursor-move\"></div>
                {% endif %}
        
                {% for element in row.getElements %}

                    {% if element.isInstanceOf('Gibbon\\\\Forms\\\\Layout\\\\Heading') %}
                        {% set class = 'flex-grow justify-center' %}
                    {% elseif element.isInstanceOf('Gibbon\\\\Forms\\\\Layout\\\\Label') %}
                        {% set class = 'sm:w-2/5 flex flex-col justify-center sm:mb-0' %}
                    {% elseif element.isInstanceOf('Gibbon\\\\Forms\\\\Layout\\\\Column')  %}
                        {% set class = loop.last and loop.length == 2 ? 'flex-1 relative flex justify-end items-center' : '' %}
                    {% elseif loop.last and (loop.length == 2 or \"noIntBorder\" in form.getClass) %}
                        {% set class = 'flex-1 relative flex justify-end items-center' %}
                    {% else %}
                        {% set class = '' %}
                    {% endif %}

                    {% set hasClass = element.instanceOf('Gibbon\\\\Forms\\\\Layout\\\\Element') or element.instanceOf('Gibbon\\\\Forms\\\\Layout\\\\Column') %}
                    {% set class = hasClass ? class ~ ' ' ~  element.getClass : class %}
                    <div class=\"{{ class }} {{ 'flex-' not in class and section != 'submit' ? element.getOuterClass|default('flex-1') }}\" {{ element.getAttributeString('x-show')|raw }}>

                        {% if useSaveWarning and element.getAttribute('id') == 'Submit' %}
                            <span x-cloak x-show=\"changed\" class=\"tag message mr-4 whitespace-nowrap\">{{ __('Unsaved Changes') }}</span>
                        {% endif %}

                        {% if element.getAttribute('id') == 'Submit' %}
                            <span x-cloak x-show=\"invalid\" class=\"tag error mr-4 whitespace-nowrap\">{{ __('Please Check Form') }}</span>
                        {% endif %}

                        {{ element.getOutput|raw }}
                    </div>
                {% endfor %}

                </div>
            {% endfor %}

            {% if useSections %}
            </section>
            {% endif %}

        {% endfor %}
        </div>

        {% if form.hasMeta and form.getMeta.getElementCount > 0 %}
        <aside class=\"hidden xl:flex flex-col col-span-2 h-min bg-gray-50 rounded border border-gray-400 border-t-4 border-t-gray-400\">
            {% for element in form.getMeta.getElements %}
                {% set hasClass = element.instanceOf('Gibbon\\\\Forms\\\\Layout\\\\Element') or element.instanceOf('Gibbon\\\\Forms\\\\Layout\\\\Row') %}
            
                <div class=\"p-4 {{ not loop.last ? 'border-b'}} {{ hasClass ? element.getClass }}\" 
                    {{ element.getAttributeString('', 'id,class')|raw }}>
                    {{ element.getOutput|raw }}
                </div>
            {% endfor %}

            <template x-if=\"invalid\">
                <div class=\"p-4 border-t\">
                    <span class=\"tag error\">{{ __('Please check these fields') }}:</span>

                    <ul class=\"ml-6\">
                    <template x-for=\"v in \$validate.data(\$refs.form)\" >
                        <template x-if=\"!v.valid && v.node.labels.length > 0\">
                        <li class=\"py-0.5\">
                            <a x-bind:href=\"'#' + v.name\" target=\"_self\" x-text=\"v.node.labels[0].ariaLabel\" :aria-label=\"v.name\" class=\"text-sm hover:underline\"></a>
                        </li>
                        </template>
                    </template>
                    

                    </ul>
                </div>
            </template>
        </aside>
        {% endif %}

    </div>
    {% endif %}

    {% if postScript %}
    <p>{{ postScript|raw }}</p>
    {% endif %}

    <script type=\"text/javascript\">
        {% if quickSave and form.getAction %}
        \$(document).keydown(function(event) {
            if (!((String.fromCharCode(event.which).toLowerCase() == 's' || event.keyCode == 13) && event.metaKey) && !(event.which == 19)) return true;
            event.preventDefault();
            return false;
        });
        {% endif %}

        {% for code in javascript %}
            {{ code|raw }}
        {% endfor %}
    </script>

{% if form.getAction != 'ajax' %}
</form>
{% endif %}
", "components/form.twig.html", "/var/www/html/gibbon/resources/templates/components/form.twig.html");
    }
}
