<?php

namespace EMS\CoreBundle\Form\Field;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\ChoiceList\Loader\CallbackChoiceLoader;
use Doctrine\Bundle\DoctrineBundle\Registry;
use EMS\CoreBundle\Repository\FilterRepository;
use EMS\CoreBundle\Entity\Filter;


class AnalyzerOptionsType extends AbstractType {
	/**@var Registry $doctrine */
	private $doctrine;
	
	public function __construct(Registry $doctrine) {//'@doctrine'
		$this->doctrine = $doctrine;
	}
	
	
	/**
	 *
	 * {@inheritdoc}
	 *
	 */
	public function buildForm(FormBuilderInterface $builder, array $options) {
		$builder->add ( 'type', ChoiceType::class, [
				'choices' => [
						'Standard' => 'standard',
						'Stop' => 'stop',
						'Pattern' => 'pattern',
						'Fingerprint' => 'fingerprint',
						'Custom' => 'custom',
				],
		] )->add ( 'tokenizer', ChoiceType::class, [
				'attr' => ['class' => 'analyzer_option'],
				'required' => false,
				'choices' => [
						'Standard' => 'standard',
						'Letter' => 'letter',
						'Lowercase' => 'lowercase',
						'Whitespace' => 'whitespace',
						'UAX URL Email' => 'uax_url_email',
						'Classic' => 'classic',
						'Thai' => 'thai',
						'N-Gram' => 'ngram',
						'Edge N-Gram' => 'edge_ngram',
						'Keyword' => 'keyword',
						'Pattern' => 'pattern',
						'Path' => 'path_hierarchy',
				],
		] )->add ( 'max_token_length', IntegerType::class, [
				'attr' => ['class' => 'analyzer_option'],
				'required' => false,
		] )->add ( 'max_output_size', IntegerType::class, [
				'attr' => ['class' => 'analyzer_option'],
				'required' => false,
		] )->add ( 'lowercase', CheckboxType::class, [
				'attr' => ['class' => 'analyzer_option'],
				'required' => false,
		] )->add ( 'pattern', TextType::class, [
				'attr' => ['class' => 'analyzer_option'],
				'required' => false,
		] )->add ( 'separator', TextType::class, [
				'attr' => ['class' => 'analyzer_option'],
				'required' => false,
		] )->add ( 'flags', ChoiceType::class, [
				'attr' => ['class' => 'analyzer_option'],
				'required' => false,
				'choices' => [
						'CANON_EQ' => 'CANON_EQ',
						'CASE_INSENSITIVE' => 'CASE_INSENSITIVE',
						'COMMENTS' => 'COMMENTS',
						'DOTALL' => 'DOTALL',
						'LITERAL' => 'LITERAL',
						'MULTILINE' => 'MULTILINE',
						'UNICODE_CASE' => 'UNICODE_CASE',
						'UNICODE_CHARACTER_CLASS' => 'UNICODE_CHARACTER_CLASS',
						'UNIX_LINES' => 'UNIX_LINES',
				],
				'multiple' => true,
		] )->add ( 'char_filter', ChoiceType::class, [
				'required' => false,
				'attr' => ['class' => 'analyzer_option'],
				'choices' => [
						'HTML Strip' => 'html_strip',
				],
				'multiple' => true,
		] )->add ( 'filter', ChoiceType::class, [
				'attr' => ['class' => 'analyzer_option'],
				'required' => false,
				'choice_loader' => new CallbackChoiceLoader(function() {
					$out = [
						'Built-in' => [
									'Standard' => 'standard',
									'ASCII Folding' => 'asciifolding',
									'Flatten graph' => 'flatten_graph',
									'Lowercase' => 'lowercase',
									'Uppercase' => 'uppercase',
									'NGram' => 'nGram',
									'Edge NGram' => 'edgeNGram',
									'Porter Stem' => 'porter_stem',
									'Stop' => 'stop',
									'Word Delimiter' => 'word_delimiter',
						],
						'Customised' =>[
					
						],
					];
					
					/**@var FilterRepository $repository*/
					$repository = $this->doctrine->getRepository('EMSCoreBundle:Filter');
					/**@var Filter $filter*/
					foreach ($repository->findAll() as $filter) {
						$out['Customised'][$filter->getLabel()] = $filter->getName();
					}
				
					return $out;
				}),
				'multiple' => true,
		] )->add ( 'stopwords', ChoiceType::class, [
				'attr' => ['class' => 'analyzer_option'],
				'required' => false,
				'choices' => [
						'None' => '_none_',
						'Arabic' => '_arabic_',
						'Armenian' => '_armenian_',
						'Basque' => '_basque_',
						'Brazilian' => '_brazilian_',
						'Bulgarian' => '_bulgarian_',
						'Catalan' => '_catalan_',
						'Cjk' => '_cjk_',
						'Czech' => '_czech_',
						'Danish' => '_danish_',
						'Dutch' => '_dutch_',
						'English' => '_english_',
						'Finnish' => '_finnish_',
						'French' => '_french_',
						'Galician' => '_galician_',
						'German' => '_german_',
						'Greek' => '_greek_',
						'Hindi' => '_hindi_',
						'Hungarian' => '_hungarian_',
						'Indonesian' => '_indonesian_',
						'Irish' => '_irish_',
						'Italian' => '_italian_',
						'Latvian' => '_latvian_',
						'Lithuanian' => '_lithuanian_',
						'Norwegian' => '_norwegian_',
						'Persian' => '_persian_',
						'Portuguese' => '_portuguese_',
						'Romanian' => '_romanian_',
						'Russian' => '_russian_',
						'Sorani' => '_sorani_',
						'Spanish' => '_spanish_',
						'Swedish' => '_swedish_',
						'Turkish' => '_turkish_',
						'Thai' => '_thai_',
				],
		] )->add ( 'position_increment_gap', IntegerType::class, [
				'attr' => ['class' => 'analyzer_option'],
				'required' => false,
		] );
	}
}