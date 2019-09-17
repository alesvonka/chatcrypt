<?php

declare(strict_types=1);

namespace App\Presenters;

use App\Model\Emoji;
use Nette;
use Nette\Application\UI\Form;


final class HomepagePresenter extends Nette\Application\UI\Presenter
{
    const ENCRYPT_METHOD    = 'AES-256-CBC';
    private $secret_iv      = 'This is my secret iv';
    private $database;
    public  $secret_key;
    public  $nickname;
    public  $group;
    public $ischat  = false;
    public $chat    = [];
    public $last_message = false;

    /** @var Emoji @inject */
    public $emoji;

    public $users= [];
    public $ip;

    /** @var Nette\Http\SessionSection */
    private $sessionSection;

    public function __construct(Nette\Database\Context $database, Nette\Http\Session $session)
    {
        $this->database = $database;
        $this->database->table('chat')->where('message_date < SUBDATE(NOW(), INTERVAL 1 HOUR)')->delete();
        $this->sessionSection   = $session->getSection('cryptchat');

        if(!$this->sessionSection->onOff)
        {
            $this->sessionSection->onOff = true;
        }
    }

    public function startup()
    {
        parent::startup();
        $this->ip = $this->getHttpRequest()->getRemoteAddress();

    }

    public function handleSetOnOffNewMessageVoice()
    {
        $this->redrawControl('setOnOffNewMessageVoice');
        $this->sessionSection->onOff = ($this->sessionSection->onOff == true) ? false : true;
    }

    public function handleRefresh($group, $nickname, $secret_key)
    {
        $this->nickname     = $nickname;
        $this->group        = $group;
        $this->secret_key   = $secret_key;

        $this->setChat();
        $this->redrawControl('refreshChat');
    }

    /**
     *
     * @param string $action: can be 'encrypt' or 'decrypt'
     * @param string $string: string to encrypt or decrypt
     *
     * @return string
     */
    public function encrypt_decrypt($action, $string) {
        $output = false;
        // hash
        $key = hash('sha256', $this->secret_key);

        // iv - encrypt method AES-256-CBC expects 16 bytes - else you will get a warning
        $iv = substr(hash('sha256', $this->secret_iv), 0, 16);
        if ( $action == 'encrypt' ) {
            $output = openssl_encrypt($string, self::ENCRYPT_METHOD, $key, 4, $iv);
            $output = base64_encode($output);
        } else if( $action == 'decrypt' ) {
            $output = openssl_decrypt(base64_decode($string), self::ENCRYPT_METHOD, $key, 4, $iv);
        }
        return $output;
    }

    private function setChat()
    {
        $items = $this->database->table('chat')->where('group',$this->encrypt_decrypt('encrypt',$this->group))->order('message_date ASC');

        $last_id   = null;
        $last_user = null;

        foreach ($items AS $key=> $item)
        {
            $this->chat[$key]['user']           = $this->encrypt_decrypt('decrypt',$item->user);
            $this->chat[$key]['ip']             = $this->encrypt_decrypt('decrypt',$item->ip);
            $this->chat[$key]['message']        = $this->encrypt_decrypt('decrypt',$item->message);
            $this->chat[$key]['message_date']   = $item->message_date;

            $this->users[$item->ip.$item->user] = $this->chat[$key]['user'];

            $last_id   = $key;
            $last_user = $this->chat[$key]['user'];
        }

        //play / noplay new message
        if($last_user != $this->nickname && $this->sessionSection->id != $last_id)
        {
            $this->sessionSection->id   = $last_id;
            $this->last_message         = true;

        }elseif($last_user == $this->nickname)
        {
            $this->sessionSection->id   = $last_id;
        }else{
            $this->last_message         = false;
        }

        $this->ischat = true;
    }

    protected function createComponentLogin(string $name): Form
    {
        $form = new Form();
        $form->getElementPrototype()->class('ajax text-light small');
        $form->addText('nickname',null)
            ->setHtmlAttribute('autocomplete','off')
            ->setHtmlAttribute('placeholder','Nickname')
            ->setRequired(true);
        $form->addText('group',null)
            ->setHtmlAttribute('autocomplete','off')
            ->setHtmlAttribute('placeholder','Group')
            ->setRequired(true);
        $form->addPassword('password',null)
            ->setHtmlAttribute('autocomplete','off')
            ->setHtmlAttribute('placeholder','Password Group')
            ->setRequired(true);
        $form->addSubmit('submit','Create group / Join to group');
        $form->onSuccess[] = [$this,'loginFormSuccess'];
        return $form;
    }

    public function loginFormSuccess(Form $form, Nette\Utils\ArrayHash $values): Form
    {
        $this->nickname     = $values['nickname'];
        $this->group        = $values['group'];
        $this->secret_key   = $values['password'];

        $this->setChat();

        $isDuplicateUser = 0;
        foreach ($this->users as $nickname)
        {
            $isDuplicateUser += ($this->nickname == $nickname)? 1:0;
        }

        if($isDuplicateUser > 1){
            $this['login']['nickname']->addError('Your nickname is exist!');
            $this->redrawControl('flashes');
            $this->ischat = false;
        }

        $this->redrawControl('loginForm');
        $this->redrawControl('refresh');
        return $form;
    }

    protected function createComponentChatForm(string $name): Form
    {
        $form = new Form();
        $form->getElementPrototype()->class('text-light small ajax');
        $form->addHidden('nickname')->setDefaultValue($this->nickname);
        $form->addHidden('group')->setDefaultValue($this->group);
        $form->addHidden('password')->setDefaultValue($this->secret_key);
        $form->addText('message',null)
            ->setHtmlAttribute('class','form-control form-control-sm mt-1 mb-1')
            ->setHtmlAttribute('placeholder','Message')
            ->setHtmlAttribute('autofocus','autofocus')
            ->setHtmlAttribute('autocomplete','off');
        $form->addSubmit('submit','Send')
            ->setHtmlAttribute('class','btn btn-sm btn-dark text-success');
        $form->onSuccess[] = [$this,'chatFormSuccess'];
        return $form;
    }

    public function chatFormSuccess(Form $form, Nette\Utils\ArrayHash $values): Form
    {

        $this->nickname     = $values['nickname'];
        $this->group        = $values['group'];
        $this->secret_key   = $values['password'];

        if(!empty($values['message']))
        {

            //replace emoji
            foreach ($this->emoji->emoji() as $key =>$em)
            {
                $values['message'] = str_replace('[#'.$key.']','<span class="ec '.$em.'"></span>',$values['message']);
            }

            $this->database->table('chat')->insert([
                'user'      => $this->encrypt_decrypt('encrypt',$values['nickname']),
                'ip'        => $this->encrypt_decrypt('encrypt',$this->ip.$values['nickname']),
                'group'     => $this->encrypt_decrypt('encrypt',$values['group']),
                'message'   => $this->encrypt_decrypt('encrypt',$values['message']),
            ]);
        }

        $this['chatForm']['message']->value = '';
        $this->ischat = true;
        $this->setChat();
        $this->redrawControl('loginForm');
        return $form;
    }

    public function renderDefault()
    {
        $this->template->nickname                   = $this->nickname;
        $this->template->group                      = $this->group;
        $this->template->secret_key                 = $this->secret_key;
        $this->template->chat                       = $this->chat;
        $this->template->last_message               = $this->last_message;
        $this->template->ischat                     = $this->ischat;
        $this->template->users                      = $this->users;
        $this->template->emoji                      = $this->emoji->emoji();
        $this->template->setOnOffNewMessageVoice    = $this->sessionSection->onOff;
    }
}
