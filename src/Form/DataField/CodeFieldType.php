<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Form\DataField;

use EMS\CoreBundle\Form\Field\AnalyzerPickerType;
use EMS\CoreBundle\Form\Field\IconPickerType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\OptionsResolver\OptionsResolver;

class CodeFieldType extends DataFieldType
{
    #[\Override]
    public function getLabel(): string
    {
        return 'Code editor field';
    }

    #[\Override]
    public static function getIcon(): string
    {
        return 'fa fa-code';
    }

    #[\Override]
    public function getParent(): string
    {
        return HiddenType::class;
    }

    /**
     * @param FormInterface<mixed> $form
     * @param array<string, mixed> $options
     */
    #[\Override]
    public function buildView(FormView $view, FormInterface $form, array $options): void
    {
        $fieldType = $options['metadata'];

        /* get options for twig context */
        parent::buildView($view, $form, $options);
        $view->vars['icon'] = $options['icon'];

        $attr = $view->vars['attr'];
        if (empty($attr['class'])) {
            $attr['class'] = '';
        }

        $attr['data-max-lines'] = $options['maxLines'];
        $attr['data-language'] = $options['language'];
        $attr['data-height'] = $options['height'];
        $attr['data-theme'] = $options['theme'];
        $attr['data-disabled'] = !$this->authorizationChecker->isGranted($fieldType->getMinimumRole());
        $attr['class'] .= ' code_editor_ems';

        $view->vars['attr'] = $attr;
    }

    #[\Override]
    public function getBlockPrefix(): string
    {
        return 'codefieldtype';
    }

    #[\Override]
    public function configureOptions(OptionsResolver $resolver): void
    {
        /* set the default option value for this kind of compound field */
        parent::configureOptions($resolver);
        $resolver->setDefault('icon', null);
        $resolver->setDefault('language', null);
        $resolver->setDefault('theme', null);
        $resolver->setDefault('maxLines', 15);
        $resolver->setDefault('height', false);
        $resolver->setDefault('required', false);
    }

    #[\Override]
    public function buildOptionsForm(FormBuilderInterface $builder, array $options): void
    {
        parent::buildOptionsForm($builder, $options);
        $optionsForm = $builder->get('options');

        if ($optionsForm->has('mappingOptions')) {
            $optionsForm->get('mappingOptions')->add('analyzer', AnalyzerPickerType::class);
        }
        $optionsForm->get('displayOptions')->add('icon', IconPickerType::class, [
            'required' => false,
        ])->add('maxLines', IntegerType::class, [
            'required' => false,
        ])->add('height', IntegerType::class, [
            'required' => false,
        ])->add('language', ChoiceType::class, [
            'required' => false,
            'attr' => [
                'class' => 'select2',
            ],
            'choices' => [
                'Abap' => 'ace/mode/abap',
                'Abc' => 'ace/mode/abc',
                'Actionscript' => 'ace/mode/actionscript',
                'Ada' => 'ace/mode/ada',
                'Alda' => 'ace/mode/alda',
                'Apache conf' => 'ace/mode/apache_conf',
                'Apex' => 'ace/mode/apex',
                'Applescript' => 'ace/mode/applescript',
                'Aql' => 'ace/mode/aql',
                'Asciidoc' => 'ace/mode/asciidoc',
                'Asl' => 'ace/mode/asl',
                'Assembly x86' => 'ace/mode/assembly_x86',
                'Astro' => 'ace/mode/astro',
                'Autohotkey' => 'ace/mode/autohotkey',
                'Batchfile' => 'ace/mode/batchfile',
                'Bibtex' => 'ace/mode/bibtex',
                'C9search' => 'ace/mode/c9search',
                'C/C++' => 'ace/mode/c_cpp',
                'Cirru' => 'ace/mode/cirru',
                'Clojure' => 'ace/mode/clojure',
                'Cobol' => 'ace/mode/cobol',
                'Coffee' => 'ace/mode/coffee',
                'Coldfusion' => 'ace/mode/coldfusion',
                'Crystal' => 'ace/mode/crystal',
                'Csharp' => 'ace/mode/csharp',
                'Csound Document' => 'ace/mode/csound_document',
                'Csound Orchestra' => 'ace/mode/csound_orchestra',
                'Csound Score' => 'ace/mode/csound_score',
                'Csp' => 'ace/mode/csp',
                'Css' => 'ace/mode/css',
                'Curly' => 'ace/mode/curly',
                'Cuttlefish' => 'ace/mode/cuttlefish',
                'D' => 'ace/mode/d',
                'Dart' => 'ace/mode/dart',
                'Diff' => 'ace/mode/diff',
                'Django' => 'ace/mode/django',
                'Dockerfile' => 'ace/mode/dockerfile',
                'Dot' => 'ace/mode/dot',
                'Drools' => 'ace/mode/drools',
                'Edifact' => 'ace/mode/edifact',
                'Eiffel' => 'ace/mode/eiffel',
                'Ejs' => 'ace/mode/ejs',
                'Elixir' => 'ace/mode/elixir',
                'Elm' => 'ace/mode/elm',
                'Erlang' => 'ace/mode/erlang',
                'Flix' => 'ace/mode/flix',
                'Forth' => 'ace/mode/forth',
                'Fortran' => 'ace/mode/fortran',
                'Fsharp' => 'ace/mode/fsharp',
                'Fsl' => 'ace/mode/fsl',
                'Ftl' => 'ace/mode/ftl',
                'Gcode' => 'ace/mode/gcode',
                'Gherkin' => 'ace/mode/gherkin',
                'Gitignore' => 'ace/mode/gitignore',
                'Glsl' => 'ace/mode/glsl',
                'Gobstones' => 'ace/mode/gobstones',
                'Golang' => 'ace/mode/golang',
                'Graphqlschema' => 'ace/mode/graphqlschema',
                'Groovy' => 'ace/mode/groovy',
                'Haml' => 'ace/mode/haml',
                'Handlebars' => 'ace/mode/handlebars',
                'Haskell' => 'ace/mode/haskell',
                'Haskell Cabal' => 'ace/mode/haskell_cabal',
                'Haxe' => 'ace/mode/haxe',
                'Hjson' => 'ace/mode/hjson',
                'Html' => 'ace/mode/html',
                'Html Elixir' => 'ace/mode/html_elixir',
                'Html Ruby' => 'ace/mode/html_ruby',
                'Ini' => 'ace/mode/ini',
                'Io' => 'ace/mode/io',
                'Ion' => 'ace/mode/ion',
                'Jack' => 'ace/mode/jack',
                'Jade' => 'ace/mode/jade',
                'Java' => 'ace/mode/java',
                'Javascript' => 'ace/mode/javascript',
                'Jexl' => 'ace/mode/jexl',
                'Json' => 'ace/mode/json',
                'Json5' => 'ace/mode/json5',
                'Jsoniq' => 'ace/mode/jsoniq',
                'Jsp' => 'ace/mode/jsp',
                'Jssm' => 'ace/mode/jssm',
                'Jsx' => 'ace/mode/jsx',
                'Julia' => 'ace/mode/julia',
                'Kotlin' => 'ace/mode/kotlin',
                'Latex' => 'ace/mode/latex',
                'Latte' => 'ace/mode/latte',
                'Less' => 'ace/mode/less',
                'Liquid' => 'ace/mode/liquid',
                'Lisp' => 'ace/mode/lisp',
                'Livescript' => 'ace/mode/livescript',
                'Logiql' => 'ace/mode/logiql',
                'Logtalk' => 'ace/mode/logtalk',
                'Lsl' => 'ace/mode/lsl',
                'Lua' => 'ace/mode/lua',
                'Luapage' => 'ace/mode/luapage',
                'Lucene' => 'ace/mode/lucene',
                'Makefile' => 'ace/mode/makefile',
                'Markdown' => 'ace/mode/markdown',
                'Mask' => 'ace/mode/mask',
                'Matlab' => 'ace/mode/matlab',
                'Maze' => 'ace/mode/maze',
                'Mediawiki' => 'ace/mode/mediawiki',
                'Mel' => 'ace/mode/mel',
                'Mips' => 'ace/mode/mips',
                'Mixal' => 'ace/mode/mixal',
                'Mushcode' => 'ace/mode/mushcode',
                'Mysql' => 'ace/mode/mysql',
                'Nasal' => 'ace/mode/nasal',
                'Nginx' => 'ace/mode/nginx',
                'Nim' => 'ace/mode/nim',
                'Nix' => 'ace/mode/nix',
                'Nsis' => 'ace/mode/nsis',
                'Nunjucks' => 'ace/mode/nunjucks',
                'Objectivec' => 'ace/mode/objectivec',
                'Ocaml' => 'ace/mode/ocaml',
                'Odin' => 'ace/mode/odin',
                'Partiql' => 'ace/mode/partiql',
                'Pascal' => 'ace/mode/pascal',
                'Perl' => 'ace/mode/perl',
                'Pgsql' => 'ace/mode/pgsql',
                'Php' => 'ace/mode/php',
                'PHP Laravel blade' => 'ace/mode/php_laravel_blade',
                'Pig' => 'ace/mode/pig',
                'Plain_ ext' => 'ace/mode/plain_text',
                'Plsql' => 'ace/mode/plsql',
                'Powershell' => 'ace/mode/powershell',
                'Praat' => 'ace/mode/praat',
                'Prisma' => 'ace/mode/prisma',
                'Prolog' => 'ace/mode/prolog',
                'Properties' => 'ace/mode/properties',
                'Protobuf' => 'ace/mode/protobuf',
                'Prql' => 'ace/mode/prql',
                'Puppet' => 'ace/mode/puppet',
                'Python' => 'ace/mode/python',
                'Qml' => 'ace/mode/qml',
                'R' => 'ace/mode/r',
                'Raku' => 'ace/mode/raku',
                'Razor' => 'ace/mode/razor',
                'Rdoc' => 'ace/mode/rdoc',
                'Red' => 'ace/mode/red',
                'Redshift' => 'ace/mode/redshift',
                'Rhtml' => 'ace/mode/rhtml',
                'Robot' => 'ace/mode/robot',
                'Rst' => 'ace/mode/rst',
                'Ruby' => 'ace/mode/ruby',
                'Rust' => 'ace/mode/rust',
                'Sac' => 'ace/mode/sac',
                'Sass' => 'ace/mode/sass',
                'Scad' => 'ace/mode/scad',
                'Scala' => 'ace/mode/scala',
                'Scheme' => 'ace/mode/scheme',
                'Scrypt' => 'ace/mode/scrypt',
                'Scss' => 'ace/mode/scss',
                'Sh' => 'ace/mode/sh',
                'Sjs' => 'ace/mode/sjs',
                'Slim' => 'ace/mode/slim',
                'Smarty' => 'ace/mode/smarty',
                'Smithy' => 'ace/mode/smithy',
                'Snippets' => 'ace/mode/snippets',
                'Soy template' => 'ace/mode/soy_template',
                'Space' => 'ace/mode/space',
                'Sparql' => 'ace/mode/sparql',
                'Sql' => 'ace/mode/sql',
                'Sqlserver' => 'ace/mode/sqlserver',
                'Stylus' => 'ace/mode/stylus',
                'Svg' => 'ace/mode/svg',
                'Swift' => 'ace/mode/swift',
                'Tcl' => 'ace/mode/tcl',
                'Terraform' => 'ace/mode/terraform',
                'Tex' => 'ace/mode/tex',
                'Text' => 'ace/mode/text',
                'Textile' => 'ace/mode/textile',
                'Toml' => 'ace/mode/toml',
                'Tsx' => 'ace/mode/tsx',
                'Turtle' => 'ace/mode/turtle',
                'Twig' => 'ace/mode/twig',
                'Typescript' => 'ace/mode/typescript',
                'Vala' => 'ace/mode/vala',
                'Vbscript' => 'ace/mode/vbscript',
                'Velocity' => 'ace/mode/velocity',
                'Verilog' => 'ace/mode/verilog',
                'Vhdl' => 'ace/mode/vhdl',
                'Visualforce' => 'ace/mode/visualforce',
                'Wollok' => 'ace/mode/wollok',
                'Xml' => 'ace/mode/xml',
                'Xquery' => 'ace/mode/xquery',
                'Yaml' => 'ace/mode/yaml',
                'Zeek' => 'ace/mode/zeek',
            ],
        ])->add('theme', ChoiceType::class, [
            'required' => false,
            'attr' => [
                'class' => 'select2',
            ],
            'choices' => [
                'Ambiance' => 'ace/theme/ambiance',
                'Chaos' => 'ace/theme/chaos',
                'Chrome' => 'ace/theme/chrome',
                'Cloud9 day' => 'ace/theme/cloud9_day',
                'Cloud9 night' => 'ace/theme/cloud9_night',
                'Cloud9 night low color' => 'ace/theme/cloud9_night_low_color',
                'Cloud editor' => 'ace/theme/cloud_editor',
                'Cloud editor_dark' => 'ace/theme/cloud_editor_dark',
                'Clouds' => 'ace/theme/clouds',
                'Clouds midnight' => 'ace/theme/clouds_midnight',
                'Cobalt' => 'ace/theme/cobalt',
                'Crimson editor' => 'ace/theme/crimson_editor',
                'Dawn' => 'ace/theme/dawn',
                'Dracula' => 'ace/theme/dracula',
                'Dreamweaver' => 'ace/theme/dreamweaver',
                'Eclipse' => 'ace/theme/eclipse',
                'Github' => 'ace/theme/github',
                'Github dark' => 'ace/theme/github_dark',
                'Gob' => 'ace/theme/gob',
                'Gruvbox' => 'ace/theme/gruvbox',
                'Gruvbox dark hard' => 'ace/theme/gruvbox_dark_hard',
                'Gruvbox light hard' => 'ace/theme/gruvbox_light_hard',
                'Idle fingers' => 'ace/theme/idle_fingers',
                'Iplastic' => 'ace/theme/iplastic',
                'Katzenmilch' => 'ace/theme/katzenmilch',
                'Kr theme' => 'ace/theme/kr_theme',
                'Kuroir' => 'ace/theme/kuroir',
                'Merbivore' => 'ace/theme/merbivore',
                'Merbivore soft' => 'ace/theme/merbivore_soft',
                'Mono industrial' => 'ace/theme/mono_industrial',
                'Monokai' => 'ace/theme/monokai',
                'Nord dark' => 'ace/theme/nord_dark',
                'One dark' => 'ace/theme/one_dark',
                'Pastel on dark' => 'ace/theme/pastel_on_dark',
                'Solarized dark' => 'ace/theme/solarized_dark',
                'Solarized light' => 'ace/theme/solarized_light',
                'Sqlserver' => 'ace/theme/sqlserver',
                'Terminal' => 'ace/theme/terminal',
                'Textmate' => 'ace/theme/textmate',
                'Tomorrow' => 'ace/theme/tomorrow',
                'Tomorrow night' => 'ace/theme/tomorrow_night',
                'Tomorrow night blue' => 'ace/theme/tomorrow_night_blue',
                'Tomorrow night bright' => 'ace/theme/tomorrow_night_bright',
                'Tomorrow night eighties' => 'ace/theme/tomorrow_night_eighties',
                'Twilight' => 'ace/theme/twilight',
                'Vibrant ink' => 'ace/theme/vibrant_ink',
                'Xcode' => 'ace/theme/xcode',
            ],
        ]);
    }
}
