<?php

namespace EMS\CoreBundle\Form\Field;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\TextType;

class AnalyzerOptionsType extends AbstractType {
	
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
		] )->add ( 'max_token_length', IntegerType::class, [
				'required' => false
		] )->add ( 'max_output_size', IntegerType::class, [
				'required' => false
		] )->add ( 'position_increment_gap', IntegerType::class, [
				'required' => false
		] )->add ( 'lowercase', CheckboxType::class, [
				'required' => false
		] )->add ( 'pattern', TextType::class, [
				'required' => false,
		] )->add ( 'separator', TextType::class, [
				'required' => false,
		] )->add ( 'flags', ChoiceType::class, [
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
				'choices' => [
						'HTML Strip' => 'html_strip',
				],
				'multiple' => true,
		] )->add ( 'filter', ChoiceType::class, [
				'choices' => [
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
				'multiple' => true,
		] )->add ( 'tokenizer', ChoiceType::class, [
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
		] )->add ( 'stopwords', ChoiceType::class, [
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
		] );
	}
}