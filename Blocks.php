<?php

namespace MerapiPanel\Module\Editor {

    use MerapiPanel\Box;
    use MerapiPanel\Box\Module\__Fragment;
    use MerapiPanel\Box\Module\Entity\Module;
    use MerapiPanel\Utility\Util;
    use Symfony\Component\Filesystem\Path;

    class Blocks extends __Fragment
    {

        protected $module;
        private $styles = "";

        function onCreate(Module $module)
        {
            $this->module = $module;
        }

        function getBlocks()
        {
            $module_dirname = Path::canonicalize(Path::join(__DIR__, ".."));

            $blocks = [];
            foreach (glob(Path::join($module_dirname, "**", "Blocks", "*", "index.php")) as $block_file) {

                $data = require_once($block_file);
                if (!is_array($data)) {
                    continue;
                }

                $data = array_combine(array_keys($data), array_map(function ($item) use ($block_file) {
                    if (is_string($item) && strpos($item, "file:.") === 0) {
                        $item = str_replace("file:.", str_replace(Path::normalize($_ENV['__MP_CWD__']), "", Path::normalize(dirname($block_file))), $item);
                    }
                    return $item;
                }, array_values($data)));

                $blocks = array_merge($blocks, [$data]);
            }

            return $blocks;
        }

        private function renderTag($tagName = "div", $component = [])
        {

            $attributes = [
                ...($component['attributes'] ?? [])
            ];
            if (isset($component['classes']) && is_array($component['classes'])) {
                $attributes["class"] = implode(" ", array_filter($component['classes'], fn($v) => is_string($v)));
            }
            if (isset($component["attributes"]["style"])) {
                $selector = "";
                if (!isset($component["attributes"]["id"])) {
                    $component["attributes"]["id"] = Util::uniq(46);
                }
                $selector .= "#" . $component["attributes"]["id"];
                if (isset($component["attributes"]["classes"])) {
                    $selector .= "." . implode(".", $component["attributes"]["classes"]);
                }

                $this->styles .= "$selector{" . $component['attributes']['style'] . "}";
                unset($component["attributes"]["style"]);
            }

            $attribute = implode(" ", array_filter(array_map(function ($k) use ($attributes) {
                $val = $attributes[$k];
                if (empty($val)) return;
                return "$k=\"$val\"";
            }, array_keys($attributes))));

            $content = isset($component["components"])  ? $this->render($component['components'] ?? []) : ($component['content'] ?? "");
            return "<$tagName {$attribute}>{$content}</$tagName>";
        }


        private function renderResolve($component = [])
        {

            if (gettype($component) === "string") {
                return $component;
            }

            if (isset($component['tagName'])) {
                $attributes = [
                    ...($component['attributes'] ?? [])
                ];
                if (isset($component['classes']) && is_array($component['classes'])) {
                    $attributes["class"] = implode(" ", array_filter($component['classes'], fn($v) => is_string($v)));
                }
                if (isset($component["attributes"]["style"])) {
                    $selector = "";
                    if (!isset($component["attributes"]["id"])) {
                        $component["attributes"]["id"] = Util::uniq(46);
                    }
                    $selector .= "#" . $component["attributes"]["id"];
                    if (isset($component["attributes"]["classes"])) {
                        $selector .= "." . implode(".", $component["attributes"]["classes"]);
                    }

                    $this->styles .= "$selector{" . $component['attributes']['style'] . "}";
                    unset($component["attributes"]["style"]);
                }

                $attribute = implode(" ", array_filter(array_map(function ($k) use ($attributes) {
                    $val = $attributes[$k];
                    if (empty($val)) return;
                    return "$k=\"$val\"";
                }, array_keys($attributes))));

                if (isset($component['content'])) {
                    return "<{$component['tagName']} {$attribute}>{$component['content']}</{$component['tagName']}>";
                } else if (isset($component['components'])) {
                    return "<{$component['tagName']} {$attribute}>{$this->render($component['components'])}</{$component['tagName']}>";
                } else {
                    return "<{$component['tagName']} {$attribute}/>";
                }
            } else {

                return $this->renderTag("div", $component);
            }
        }


        function render($components = [])
        {
            if (!is_array($components)) return $components;

            if (empty($components) || gettype($components) === "string") {
                return $components;
            }

            $resolve_namespace = [
                "bs" => "Editor",
            ];

            $rendered = [];
            foreach ($components as $key => $component) {

                $type = $component['type'] ?? null;
                $attributes = isset($component['attributes']) && is_array($component['attributes']) ? $component['attributes'] : (json_decode($component['attributes'] ?? '[]', true) ?? []);
                if (isset($component['classes']) && is_array($component['classes'])) {
                    $attributes["class"] = implode(" ", array_filter($component['classes'], fn($v) => is_string($v)));
                }
                if (isset($component["attributes"]["style"])) {
                    $selector = "";
                    if (!isset($component["attributes"]["id"])) {
                        $component["attributes"]["id"] = Util::uniq(46);
                    }
                    $selector .= "#" . $component["attributes"]["id"];
                    if (isset($component["attributes"]["classes"])) {
                        $selector .= "." . implode(".", $component["attributes"]["classes"]);
                    }

                    $this->styles .= "$selector{" . $component['attributes']['style'] . "}";
                    unset($component["attributes"]["style"]);
                }

                $isAttributeEmpty = empty(array_filter(array_map(fn($v) => !empty(trim($v)), array_values($attributes))));

                if (!$type) {
                    $rendered[] = $this->renderResolve($component);
                    continue;
                }

                if (in_array($type, ["text", "textnode"])) {
                    if ($isAttributeEmpty) {
                        $rendered[] = $component['content'] ?? ($this->render($component['components'] ?? []));
                        continue;
                    }
                    $rendered[] = $this->renderTag(...[
                        "tagName"    => "span",
                        "component" => $component
                    ]);
                    continue;
                }

                if (count(explode('-', $type)) > 1) {

                    preg_match("/\w+/i", $type, $matches);
                    if (empty($matches)) {
                        $rendered[] = "<div class='text-center py-3'>Unknown type: {$type}</div>";
                        continue;
                    }

                    $module = ucfirst($resolve_namespace[$matches[0]] ?? $matches[0]);
                    $blockName = trim(str_replace($matches[0], "", $type), '-');
                } else {

                    $module = "Editor";
                    $blockName = $type;
                }

                if ((Box::module($module) instanceof Module)) {
                    $fragment = Path::join(Box::module($module)->path, "Blocks", $blockName, "render.php");
                    if (!file_exists($fragment)) {
                        $rendered[] = $this->renderTag(...[
                            "tagName"    => "div",
                            "component" => $component
                        ]);
                        continue;
                    }
                    $rendered[] = blockContext($component, $fragment, $key);
                } else {
                    $rendered[] = $this->renderTag(...[
                        "tagName"    => "div",
                        "component" => $component
                    ]);
                    continue;
                }
            }
            $output = implode("", $rendered);
            return $output;
        }

        function getStyles()
        {
            return $this->styles;
        }
    }
}





namespace {

    use MerapiPanel\Box;

    function renderComponents($components = [])
    {
        if (!is_array($components)) return $components;
        return Box::module("Editor")->Blocks->render($components);
    }

    function blockContext($component, $__fragment, $__index = 0)
    {

        extract($component);
        if (!isset($attributes)) {
            $attributes = [];
        } else {
            $attributes = array_map(function ($item) {
                if (is_object($item)) {
                    $item = implode(";", json_decode(json_encode($item), true));
                } else if (is_array($item)) {
                    $item = implode(";", $item);
                }
                return $item;
            }, is_array($attributes) ? $attributes : []);
        }
        if (!isset($classes)) {
            $classes = [];
        }

        $attributes['class'] = implode(" ", is_array($classes) ? $classes : []);

        if (!isset($components)) {
            $components = [];
        }

        ob_start();
        include $__fragment;
        return ob_get_clean();
    }
}
