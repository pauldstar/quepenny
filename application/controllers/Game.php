<?php defined('BASEPATH') OR exit('No direct script access allowed');

class Game extends QP_Controller
{
	public function __construct()
	{
		parent::__construct();
		$this->load->model('question');
	}

	function get_question_score($answer_code, $question_id, $game_level)
	{
		$question = $this->question->get_session_question($question_id, $game_level);
		$answer = self::get_answer($question, $answer_code);
		$is_correct = $answer === $question->correct_answer;
		$this->question->delete_session_question($question_id, $game_level);

		$max = $min = 0;

		switch ($question->difficulty)
		{
			case 'easy': $min = 1; $max = 33; break;
			case 'medium': $min = 33; $max = 66; break;
			case 'hard': $min = 66; $max = 100;
		}

		$rand_float = mt_rand() / mt_getrandmax();

		if ($is_correct) $score = $rand_float * ($max - $min) + $min;
		else $score = 0;

		$this->question->set_session_max_score($score);

		echo $score;
	}

	public function get_questions($game_level)
	{
		$this->question->clear_old_session_questions($game_level);

		$questions = $this->question->load_questions($game_level);
		$ssn_questions = [];
		$usr_questions = [];
		$id = 0;

		foreach($questions as $qtn)
		{
			$usr_qtn = [];
			$usr_qtn['id'] = $id;
			$usr_qtn['lvl'] = $game_level;
			$usr_qtn['question'] = $qtn->question;
			$usr_qtn['type'] = $qtn->type;
			$usr_qtn['correct'] = $qtn->correct_answer;

			if ($qtn->type === 'multiple')
			{
				$usr_qtn['options'][] = $qtn->correct_answer;
				foreach ($qtn->incorrect_answers as $ans) $usr_qtn['options'][] = $ans;
				shuffle($usr_qtn['options']);
				$qtn->options = $usr_qtn['options'];
			}

			$ssn_questions[] = $qtn;
			$usr_questions[] = $usr_qtn;
			$id++;
		}

		$this->question->set_session_questions($ssn_questions, $game_level);

		shuffle($usr_questions);
    echo json_encode($usr_questions);
	}

	private function get_answer($question, $answer_code)
	{
		switch($question->type)
		{
			case 'multiple': return $question->options[$answer_code];

			case 'boolean':
				switch ($answer_code)
				{
					case 0: return 'False';
					case 1: return 'True';
				}
		}
	}
}
