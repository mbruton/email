<?php
namespace adapt\email;

defined('ADAPT_STARTED') or die;

class model_email_part extends \adapt\model
{
    public function __construct($id = null, $data_source = null)
    {
        parent::__construct('email_part', $id, $data_source);
    }

    public function initialise()
    {
        parent::initialise();
    }

    /**
     * mget to return the unquoted content of a quoted-printable part
     * This is designed with APIs in mind so no equivalent is provided for base64
     *
     * @return string
     */
    public function mget_unquoted_text()
    {
        if ($this->content_encoding == 'quoted-printable') {
            return quoted_printable_decode($this->content);
        }

        return '';
    }
}