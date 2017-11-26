<?php

declare (strict_types=1);

namespace InternetPrintingProtocol\Utils\Printing;

use InternetPrintingProtocol\Exception\IppException;
use InternetPrintingProtocol\Utils\BasicIPP;

class PrintIPP extends BasicIPP
{
    public function printJob()
    {
        $this->_putDebug(sprintf("*************************\nDate: %s\n*************************\n\n",
            date('Y-m-d H:i:s')));

        if (!$this->_stringJob()) {
            return false;
        }

        if (is_readable($this->data)) {
            $this->_putDebug(_("Printing a FILE\n"), 3);

            $this->output = $this->stringjob;

            if ($this->setup->datatype === 'TEXT') {
                $this->output .= \chr(0x16);
            }

            $post_values = [
                'Content-Type' => 'application/ipp',
                'Data' => $this->output,
                'File' => $this->data,
            ];

            if ($this->setup->datatype === 'TEXT' && !isset($this->setup->noFormFeed)) {
                $post_values = array_merge($post_values, ['Filetype' => 'TEXT']);
            }
        } else {
            $this->_putDebug(_("Printing DATA\n"), 3);

            $this->output = $this->stringjob;
            $this->output .= $this->datahead;
            $this->output .= $this->data;
            $this->output .= $this->datatail;

            $post_values = [
                'Content-Type' => 'application/ipp',
                'Data' => $this->output,
            ];
        }

        if ($this->_sendHttp($post_values, $this->paths['printers'])) {
            if ($this->_parseServerOutput()) {
                $this->_getJobId();
                $this->_getJobUri();
                $this->_parseJobAttributes();
            } else {
                $this->jobs = array_merge($this->jobs, ['']);
                $this->jobs_uri = array_merge($this->jobs_uri, ['']);
            }
        }

        if (isset($this->serveroutput, $this->serveroutput->status)) {
            $this->status = array_merge($this->status, [$this->serveroutput->status]);

            if ($this->serveroutput->status === 'successful-ok') {
                $this->_errorLog(sprintf('printing job %s: ', $this->last_job).$this->serveroutput->status, 3);
            } else {
                $this->jobs = array_merge($this->jobs, ['']);
                $this->jobs_uri = array_merge($this->jobs_uri, ['']);
                $this->_errorLog(sprintf('printing job: %s', $this->last_job).$this->serveroutput->status, 1);
                if ($this->with_exceptions) {
                    throw new IppException(sprintf('job status: %s',
                        $this->serveroutput->status));
                }
            }

            return $this->serveroutput->status;
        }

        $this->status = array_merge($this->status, ['OPERATION FAILED']);
        $this->jobs = array_merge($this->jobs, ['']);
        $this->jobs_uri = array_merge($this->jobs_uri, ['']);
        $this->_errorLog('printing job : OPERATION FAILED', 1);

        return false;
    }

    protected function _getJobId()
    {
        if (!isset($this->serveroutput->response)) {
            $this->jobs = array_merge($this->jobs, ['NO JOB']);
        }

        $jobfinded = false;
        for ($i = 0, $loopsMax = array_key_exists($i, $this->serveroutput->response); !$jobfinded && $loopsMax; ++$i) {
            if ($this->serveroutput->response[$i]['attributes'] === 'job-attributes') {
                for ($j = 0; array_key_exists($j, $this->serveroutput->response[$i]); ++$j) {
                    if ($this->serveroutput->response[$i][$j]['name'] === 'job-id') {
                        $this->last_job = $this->serveroutput->response[$i][$j]['value'];
                        $this->jobs = array_merge($this->jobs, [$this->serveroutput->response[$i][$j]['value']]);

                        return;
                    }
                }
            }
        }
    }

    protected function _getJobUri()
    {
        if (!isset($this->jobs_uri)) {
            $this->jobs_uri = [];
        }

        $jobfinded = false;
        for ($i = 0, $loopsMax = array_key_exists($i, $this->serveroutput->response); !$jobfinded && $loopsMax; ++$i) {
            if ($this->serveroutput->response[$i]['attributes'] === 'job-attributes') {
                for ($j = 0; array_key_exists($j, $this->serveroutput->response[$i]); ++$j) {
                    if ($this->serveroutput->response[$i][$j]['name'] === 'job-uri') {
                        $this->last_job = $this->serveroutput->response[$i][$j]['value'];
                        $this->jobs_uri = array_merge($this->jobs_uri, [$this->last_job]);

                        return;
                    }
                }
            }
        }
        $this->last_job = '';
    }

    protected function _parseJobAttributes()
    {
        //if (!preg_match('#successful#',$this->serveroutput->status))
        //    return false;
        $k = -1;
        $l = 0;
        foreach ($this->serveroutput->response as $i => $iValue) {
            for ($j = 0; $j < (\count($this->serveroutput->response[$i]) - 1); ++$j) {
                if (empty($this->serveroutput->response[$i][$j]['name'])) {
                    ++$l;
                    $this->parsed[$k][$l] = $this->serveroutput->response[$i][$j]['value'];
                } else {
                    ++$k;
                    $l = 0;
                    $this->parsed[$k]['range'] = $this->serveroutput->response[$i]['attributes'];
                    $this->parsed[$k]['name'] = $this->serveroutput->response[$i][$j]['name'];
                    $this->parsed[$k]['type'] = $this->serveroutput->response[$i][$j]['type'];
                    $this->parsed[$k][$l] = $this->serveroutput->response[$i][$j]['value'];
                }
            }
        }

        $this->serveroutput->response = [];

        $this->job_attributes = new \stdClass();
        foreach ($this->parsed as $i => $iValue) {
            $name = $this->parsed[$i]['name'];
            $php_name = str_replace('-', '_', $name);
            $type = $this->parsed[$i]['type'];
            $range = $this->parsed[$i]['range'];
            $this->job_attributes->$php_name = new \stdClass();
            $this->job_attributes->$php_name->_type = $type;
            $this->job_attributes->$php_name->_range = $range;
            for ($j = 0; $j < (\count($this->parsed[$i]) - 3); ++$j) {
                $value = $this->parsed[$i][$j];
                $index = '_value'.$j;
                $this->job_attributes->$php_name->$index = $value;
            }
        }

        $this->parsed = [];
    }

    public function cancelJob($job_uri)
    {
        $this->jobs = array_merge($this->jobs, ['']);
        $this->jobs_uri = array_merge($this->jobs_uri, ['']);

        $this->_putDebug(sprintf("*************************\nDate: %s\n*************************\n\n",
            date('Y-m-d H:i:s')));

        if (!$this->_stringCancel($job_uri)) {
            return false;
        }

        $this->_putDebug(_("Cancelling Job $job_uri\n"), 3);

        $this->output = $this->stringjob;

        $post_values = [
            'Content-Type' => 'application/ipp',
            'Data' => $this->output,
        ];

        if ($this->_sendHttp($post_values, $this->paths['jobs'])) {
            $this->_parseServerOutput();
        }

        if (isset($this->serveroutput, $this->serveroutput->status)) {
            $this->status = array_merge($this->status, [$this->serveroutput->status]);

            if ($this->serveroutput->status === 'successful-ok') {
                $this->_errorLog("cancelling job $job_uri: ".$this->serveroutput->status, 3);
            } else {
                $this->_errorLog("cancelling job $job_uri: ".$this->serveroutput->status, 1);
            }

            return $this->serveroutput->status;
        }

        $this->status = array_merge($this->status, ['OPERATION FAILED']);
        $this->_errorLog('cancelling job : OPERATION FAILED', 3);

        return false;
    }

    protected function _stringCancel($job_uri)
    {
        if (!isset($this->setup->charset)) {
            $this->setCharset();
        }
        if (!isset($this->setup->datatype)) {
            $this->setBinary();
        }
        if (!isset($this->setup->language)) {
            $this->setLanguage();
        }
        if (!$this->requesting_user) {
            $this->setUserName();
        }
        if (!isset($this->meta->message)) {
            $this->meta->message = '';
        }

        $this->_setOperationId();

        $this->_setJobUri($job_uri);

        if (!isset($this->error_generation->request_body_malformed)) {
            $this->error_generation->request_body_malformed = '';
        }

        $this->stringjob = \chr(0x01).\chr(0x01) // 1.1  | version-number
            .\chr(0x00).\chr(0x08) // cancel-Job | operation-id
            .$this->meta->operation_id //           request-id
            .$this->error_generation->request_body_malformed
            .\chr(0x01) // start operation-attributes | operation-attributes-tag
            .$this->meta->charset
            .$this->meta->language
            .$this->meta->job_uri
            .$this->meta->username
            .$this->meta->message
            .\chr(0x03); // end-of-attributes | end-of-attributes-tag

        $this->_putDebug(sprintf(_("String sent to the server is:\n%s\n"), $this->stringjob));

        return true;
    }

    protected function _setOperationId()
    {
        ++$this->operation_id;
        $this->meta->operation_id = $this->_integerBuild($this->operation_id);
        $this->_putDebug('operation id is: '.$this->operation_id."\n", 2);
    }

    protected function _setJobUri($job_uri)
    {
        $this->meta->job_uri = \chr(0x45) // type uri
            .\chr(0x00).\chr(0x07) // name-length
            .'job-uri'
            //. chr(0x00).chr(strlen($job_uri))
            .$this->_giveMeStringLength($job_uri)
            .$job_uri;

        $this->_putDebug('job-uri is: '.$job_uri."\n", 2);
    }

    protected function _parseResponse()
    {
        $j = -1;
        $this->index = 0;
        for ($i = $this->_parsing->offset, $iMax = \strlen($this->serveroutput->body); $i < $iMax;
            $i = $this->_parsing->offset) {
            $tag = \ord($this->serveroutput->body[$this->_parsing->offset]);

            if ($tag > 0x0F) {
                $this->_readAttribute($j);
                ++$this->index;
                continue;
            }

            switch ($tag) {
                case 0x01:
                    ++$j;
                    $this->serveroutput->response[$j]['attributes'] = 'operation-attributes';
                    $this->index = 0;
                    ++$this->_parsing->offset;
                    break;
                case 0x02:
                    ++$j;
                    $this->serveroutput->response[$j]['attributes'] = 'job-attributes';
                    $this->index = 0;
                    ++$this->_parsing->offset;
                    break;
                case 0x03:
                    ++$j;
                    $this->serveroutput->response[$j]['attributes'] = 'end-of-attributes';
                    $this->_putDebug('tag is: '.$this->serveroutput->response[$j]['attributes']."\n");
                    if ($this->alert_on_end_tag === 1) {
                        echo 'END tag OK<br />';
                    }
                    $this->response_completed[\count($this->response_completed) - 1] = 'completed';

                    return;
                case 0x04:
                    ++$j;
                    $this->serveroutput->response[$j]['attributes'] = 'printer-attributes';
                    $this->index = 0;
                    ++$this->_parsing->offset;
                    break;
                case 0x05:
                    ++$j;
                    $this->serveroutput->response[$j]['attributes'] = 'unsupported-attributes';
                    $this->index = 0;
                    ++$this->_parsing->offset;
                    break;
                default:
                    ++$j;
                    $this->serveroutput->response[$j]['attributes'] = sprintf(_('0x%x (%u) : attributes tag Unknown (reserved for future versions of IPP'),
                        $tag, $tag);
                    $this->index = 0;
                    ++$this->_parsing->offset;
                    break;
            }

            $this->_putDebug('tag is: '.$this->serveroutput->response[$j]['attributes']."\n\n\n");
        }

        return;
    }

    protected function _readAttribute($attributes_type)
    {
        $tag = \ord($this->serveroutput->body[$this->_parsing->offset]);

        ++$this->_parsing->offset;
        $j = $this->index;

        $tag = $this->_readTag($tag);

        switch ($tag) {
            case 'begCollection': //RFC3382 (BLIND CODE)
                if ($this->end_collection) {
                    --$this->index;
                }
                $this->end_collection = false;
                $this->serveroutput->response[$attributes_type][$j]['type'] = 'collection';
                $this->_putDebug("tag is: begCollection\n");
                $this->_readAttributeName($attributes_type, $j);
                if ($this->serveroutput->response[$attributes_type][$j]['name']) {
                    ++$this->collection_depth;
                    if ($this->collection_depth === 0) {
                        $this->collection = (object) 'collection';
                    }
                    if (array_key_exists($this->collection_depth, $this->collection_nbr)) {
                        ++$this->collection_nbr[$this->collection_depth];
                    } else {
                        $this->collection_nbr[$this->collection_depth] = 0;
                    }
                    unset($this->end_collection);
                } else { // it is a multi-valued collection
                    ++$this->collection_depth;
                    --$this->index;
                    ++$this->collection_nbr[$this->collection_depth];
                }
                $this->_readValue('begCollection', $attributes_type, $j);
                break;
            case 'endCollection': //RFC3382 (BLIND CODE)
                $this->serveroutput->response[$attributes_type][$j]['type'] = 'collection';
                $this->_putDebug("tag is: endCollection\n");
                $this->_readAttributeName($attributes_type, $j, 0);
                $this->_readValue('name', $attributes_type, $j, 0);
                --$this->collection_depth;
                $this->collection_key[$this->collection_depth] = 0;
                $this->end_collection = true;
                break;
            case 'memberAttrName': // RFC3382 (BLIND CODE)
                $this->serveroutput->response[$attributes_type][$j]['type'] = 'memberAttrName';
                --$this->index;
                $this->_putDebug("tag is: memberAttrName\n");
                $this->_readCollection($attributes_type, $j);
                break;

            default:
                $this->collection_depth = -1;
                $this->collection_key = [];
                $this->collection_nbr = [];
                $this->serveroutput->response[$attributes_type][$j]['type'] = $tag;
                $this->_putDebug("tag is: $tag\n");
                $attribute_name = $this->_readAttributeName($attributes_type, $j);
                if ($attribute_name) {
                    $this->attribute_name = $attribute_name;
                } else {
                    $attribute_name = $this->attribute_name;
                }

                $this->serveroutput->response[$attributes_type][$j]['value'] =
                    $this->_interpretAttribute($attribute_name, $tag,
                        $this->serveroutput->response[$attributes_type][$j]['value']);
                break;
        }

        return;
    }

    protected function _readTag($tag)
    {
        switch ($tag) {
            case 0x10:
                $tag = 'unsupported';
                break;
            case 0x11:
                $tag = "reserved for 'default'";
                break;
            case 0x12:
                $tag = 'unknown';
                break;
            case 0x13:
                $tag = 'no-value';
                break;
            case 0x15: // RFC 3380
                $tag = 'not-settable';
                break;
            case 0x16: // RFC 3380
                $tag = 'delete-attribute';
                break;
            case 0x17: // RFC 3380
                $tag = 'admin-define';
                break;
            case 0x20:
                $tag = 'IETF reserved (generic integer)';
                break;
            case 0x21:
                $tag = 'integer';
                break;
            case 0x22:
                $tag = 'boolean';
                break;
            case 0x23:
                $tag = 'enum';
                break;
            case 0x30:
                $tag = 'octetString';
                break;
            case 0x31:
                $tag = 'datetime';
                break;
            case 0x32:
                $tag = 'resolution';
                break;
            case 0x33:
                $tag = 'rangeOfInteger';
                break;
            case 0x34: //RFC3382 (BLIND CODE)
                $tag = 'begCollection';
                break;
            case 0x35:
                $tag = 'textWithLanguage';
                break;
            case 0x36:
                $tag = 'nameWithLanguage';
                break;
            case 0x37: //RFC3382 (BLIND CODE)
                $tag = 'endCollection';
                break;
            case 0x40:
                $tag = 'IETF reserved text string';
                break;
            case 0x41:
                $tag = 'textWithoutLanguage';
                break;
            case 0x42:
                $tag = 'nameWithoutLanguage';
                break;
            case 0x43:
                $tag = 'IETF reserved for future';
                break;
            case 0x44:
                $tag = 'keyword';
                break;
            case 0x45:
                $tag = 'uri';
                break;
            case 0x46:
                $tag = 'uriScheme';
                break;
            case 0x47:
                $tag = 'charset';
                break;
            case 0x48:
                $tag = 'naturalLanguage';
                break;
            case 0x49:
                $tag = 'mimeMediaType';
                break;
            case 0x4A: // RFC3382 (BLIND CODE)
                $tag = 'memberAttrName';
                break;
            case 0x7F:
                $tag = 'extended type';
                break;
            default:
                if ($tag >= 0x14 && $tag < 0x15 && $tag > 0x17 && $tag <= 0x1f) {
                    $tag = 'out-of-band';
                } elseif (0x24 <= $tag && $tag <= 0x2f) {
                    $tag = 'new integer type';
                } elseif (0x38 <= $tag && $tag <= 0x3F) {
                    $tag = 'new octet-stream type';
                } elseif (0x4B <= $tag && $tag <= 0x5F) {
                    $tag = 'new character string type';
                } elseif ((0x60 <= $tag && $tag < 0x7f) || $tag >= 0x80) {
                    $tag = 'IETF reserved for future';
                } else {
                    $tag = sprintf('UNKNOWN: 0x%x (%u)', $tag, $tag);
                }

                break;
        }

        return $tag;
    }

    // RESPONSE PARSING

    protected function _readAttributeName($attributes_type, $j, $write = 1)
    {
        $name_length = \ord($this->serveroutput->body[$this->_parsing->offset]) * 256
            + \ord($this->serveroutput->body[$this->_parsing->offset + 1]);
        $this->_parsing->offset += 2;

        $this->_putDebug('name_length '.$name_length."\n");

        $name = '';
        for ($i = 0; $i < $name_length; ++$i) {
            if ($this->_parsing->offset >= \strlen($this->serveroutput->body)) {
                return;
            }
            $name .= $this->serveroutput->body[$this->_parsing->offset];
            ++$this->_parsing->offset;
        }

        if ($write) {
            $this->serveroutput->response[$attributes_type][$j]['name'] = $name;
        }

        $this->_putDebug('name '.$name."\n");

        return $name;
    }

    protected function _readValue($attributes_type, $j, $write = 1)
    {
        $value_length = \ord($this->serveroutput->body[$this->_parsing->offset]) * 256
            + \ord($this->serveroutput->body[$this->_parsing->offset + 1]);

        $this->_putDebug('value_length '.$this->serveroutput->body[$this->_parsing->offset]
            .$this->serveroutput->body[$this->_parsing->offset + 1]
            .': '
            .$value_length
            .' ');

        $this->_parsing->offset += 2;

        $value = '';
        for ($i = 0; $i < $value_length; ++$i) {
            if ($this->_parsing->offset >= \strlen($this->serveroutput->body)) {
                return;
            }
            $value .= $this->serveroutput->body[$this->_parsing->offset];
            ++$this->_parsing->offset;
        }

        $this->_putDebug(sprintf("Value: %s\n", $value));

        if ($write) {
            $this->serveroutput->response[$attributes_type][$j]['value'] = $value;
        }

        return $value;
    }

    protected function _readCollection($attributes_type, $j)
    {
        $name_length = \ord($this->serveroutput->body[$this->_parsing->offset]) * 256
            + \ord($this->serveroutput->body[$this->_parsing->offset + 1]);

        $this->_parsing->offset += 2;

        $this->_putDebug('Collection name_length '.$name_length."\n");

        $name = '';
        for ($i = 0; $i < $name_length; ++$i) {
            $name .= $this->serveroutput->body[$this->_parsing->offset];
            ++$this->_parsing->offset;
            if ($this->_parsing->offset > \strlen($this->serveroutput->body)) {
                return;
            }
        }

        $collection_name = $name;

        $name_length = \ord($this->serveroutput->body[$this->_parsing->offset]) * 256
            + \ord($this->serveroutput->body[$this->_parsing->offset + 1]);
        $this->_parsing->offset += 2;

        $this->_putDebug('Attribute name_length '.$name_length."\n");

        $name = '';
        for ($i = 0; $i < $name_length; ++$i) {
            $name .= $this->serveroutput->body[$this->_parsing->offset];
            ++$this->_parsing->offset;
            if ($this->_parsing->offset > \strlen($this->serveroutput->body)) {
                return;
            }
        }

        $attribute_name = $name;
        if ($attribute_name === '') {
            $attribute_name = $this->last_attribute_name;
            ++$this->collection_key[$this->collection_depth];
        } else {
            $this->collection_key[$this->collection_depth] = 0;
        }
        $this->last_attribute_name = $attribute_name;

        $this->_putDebug('Attribute name '.$name."\n");

        $tag = $this->_readTag(\ord($this->serveroutput->body[$this->_parsing->offset]));
        ++$this->_parsing->offset;

        $type = $tag;

        $name_length = \ord($this->serveroutput->body[$this->_parsing->offset]) * 256
            + \ord($this->serveroutput->body[$this->_parsing->offset + 1]);
        $this->_parsing->offset += 2;

        $this->_putDebug('Collection2 name_length '.$name_length."\n");

        $name = '';
        for ($i = 0; $i < $name_length; ++$i) {
            $name .= $this->serveroutput->body[$this->_parsing->offset];
            ++$this->_parsing->offset;
            if ($this->_parsing->offset > \strlen($this->serveroutput->body)) {
                return;
            }
        }

        $collection_value = $name;
        $value_length = \ord($this->serveroutput->body[$this->_parsing->offset]) * 256
            + \ord($this->serveroutput->body[$this->_parsing->offset + 1]);

        $this->_putDebug('Collection value_length '.$this->serveroutput->body[$this->_parsing->offset]
            .$this->serveroutput->body[$this->_parsing->offset + 1]
            .': '
            .$value_length
            .' ');

        $this->_parsing->offset += 2;

        $value = '';
        for ($i = 0; $i < $value_length; ++$i) {
            if ($this->_parsing->offset >= \strlen($this->serveroutput->body)) {
                return;
            }
            $value .= $this->serveroutput->body[$this->_parsing->offset];
            ++$this->_parsing->offset;
        }

        $object = &$this->collection;
        for ($i = 0; $i <= $this->collection_depth; ++$i) {
            $indice = '_indice'.$this->collection_nbr[$i];
            if (!isset($object->$indice)) {
                $object->$indice = (object) 'indice';
            }
            $object = &$object->$indice;
        }

        $value_key = '_value'.$this->collection_key[$this->collection_depth];
        $col_name_key = '_collection_name'.$this->collection_key[$this->collection_depth];
        $col_val_key = '_collection_value'.$this->collection_key[$this->collection_depth];

        $attribute_value = $this->_interpretAttribute($attribute_name, $tag, $value);
        $attribute_name = str_replace('-', '_', $attribute_name);

        $this->_putDebug(sprintf("Value: %s\n", $value));
        $object->$attribute_name->_type = $type;
        $object->$attribute_name->$value_key = $attribute_value;
        $object->$attribute_name->$col_name_key = $collection_name;
        $object->$attribute_name->$col_val_key = $collection_value;

        $this->serveroutput->response[$attributes_type][$j]['value'] = $this->collection;
    }

    protected function _interpretAttribute($attribute_name, $type, $value)
    {
        switch ($type) {
            case 'integer':
                $value = $this->_interpretInteger($value);
                break;
            case 'rangeOfInteger':
                $value = $this->_interpretRangeOfInteger($value);
                break;
            case 'boolean':
                $value = \ord($value);
                if ($value === 0x00) {
                    $value = 'false';
                } else {
                    $value = 'true';
                }
                break;
            case 'datetime':
                $value = $this->_interpretDateTime($value);
                break;
            case 'enum':
                $value = $this->_interpretEnum($attribute_name, $value); // must be overwritten by children
                break;
            case 'resolution':
                $unit = $value[8];
                $value = $this->_interpretRangeOfInteger(substr($value, 0, 8));
                switch ($unit) {
                    case \chr(0x03):
                        $unit = 'dpi';
                        break;
                    case \chr(0x04):
                        $unit = 'dpc';
                        break;
                }
                $value = $value.' '.$unit;
                break;
            default:
                break;
        }

        return $value;
    }

    protected function _interpretRangeOfInteger($value)
    {
        $halfsize = \strlen($value) / 2;

        $integer1 = $this->_interpretInteger(substr($value, 0, $halfsize));
        $integer2 = $this->_interpretInteger(substr($value, $halfsize, $halfsize));

        return sprintf('%s-%s', $integer1, $integer2);
    }

    protected function _interpretDateTime($date)
    {
        $year = $this->_interpretInteger(substr($date, 0, 2));
        $month = $this->_interpretInteger($date[2]);
        $day = $this->_interpretInteger($date[3]);
        $hour = $this->_interpretInteger($date[4]);
        $minute = $this->_interpretInteger($date[5]);
        $second = $this->_interpretInteger($date[6]);
        $direction = $date[8];
        $hours_from_utc = $this->_interpretInteger($date[9]);
        $minutes_from_utc = $this->_interpretInteger($date[10]);

        $date = sprintf('%s-%s-%s %s:%s:%s %s%s:%s', $year, $month, $day, $hour, $minute, $second, $direction,
            $hours_from_utc, $minutes_from_utc);

        return $date;
    }

    protected function _interpretEnum($attribute_name, $value)
    {
        $value_parsed = $this->_interpretInteger($value);

        switch ($attribute_name) {
            case 'job-state':
                switch ($value_parsed) {
                    case 0x03:
                        $value = 'pending';
                        break;
                    case 0x04:
                        $value = 'pending-held';
                        break;
                    case 0x05:
                        $value = 'processing';
                        break;
                    case 0x06:
                        $value = 'processing-stopped';
                        break;
                    case 0x07:
                        $value = 'canceled';
                        break;
                    case 0x08:
                        $value = 'aborted';
                        break;
                    case 0x09:
                        $value = 'completed';
                        break;
                }
                if ($value_parsed > 0x09) {
                    $value = sprintf('Unknown(IETF standards track "job-state" reserved): 0x%x', $value_parsed);
                }
                break;
            case 'print-quality':
            case 'print-quality-supported':
            case 'print-quality-default':
                switch ($value_parsed) {
                    case 0x03:
                        $value = 'draft';
                        break;
                    case 0x04:
                        $value = 'normal';
                        break;
                    case 0x05:
                        $value = 'high';
                        break;
                }
                break;
            case 'printer-state':
                switch ($value_parsed) {
                    case 0x03:
                        $value = 'idle';
                        break;
                    case 0x04:
                        $value = 'processing';
                        break;
                    case 0x05:
                        $value = 'stopped';
                        break;
                }
                if ($value_parsed > 0x05) {
                    $value = sprintf('Unknown(IETF standards track "printer-state" reserved): 0x%x', $value_parsed);
                }
                break;

            case 'operations-supported':
                switch ($value_parsed) {
                    case 0x0000:
                    case 0x0001:
                        $value = sprintf('Unknown(reserved) : %s', \ord($value));
                        break;
                    case 0x0002:
                        $value = 'Print-Job';
                        break;
                    case 0x0003:
                        $value = 'Print-URI';
                        break;
                    case 0x0004:
                        $value = 'Validate-Job';
                        break;
                    case 0x0005:
                        $value = 'Create-Job';
                        break;
                    case 0x0006:
                        $value = 'Send-Document';
                        break;
                    case 0x0007:
                        $value = 'Send-URI';
                        break;
                    case 0x0008:
                        $value = 'Cancel-Job';
                        break;
                    case 0x0009:
                        $value = 'Get-Job-Attributes';
                        break;
                    case 0x000A:
                        $value = 'Get-Jobs';
                        break;
                    case 0x000B:
                        $value = 'Get-Printer-Attributes';
                        break;
                    case 0x000C:
                        $value = 'Hold-Job';
                        break;
                    case 0x000D:
                        $value = 'Release-Job';
                        break;
                    case 0x000E:
                        $value = 'Restart-Job';
                        break;
                    case 0x000F:
                        $value = 'Unknown(reserved for a future operation)';
                        break;
                    case 0x0010:
                        $value = 'Pause-Printer';
                        break;
                    case 0x0011:
                        $value = 'Resume-Printer';
                        break;
                    case 0x0012:
                        $value = 'Purge-Jobs';
                        break;
                    case 0x0013:
                        $value = 'Set-Printer-Attributes'; // RFC3380
                        break;
                    case 0x0014:
                        $value = 'Set-Job-Attributes'; // RFC3380
                        break;
                    case 0x0015:
                        $value = 'Get-Printer-Supported-Values'; // RFC3380
                        break;
                    case 0x0016:
                        $value = 'Create-Printer-Subscriptions';
                        break;
                    case 0x0017:
                        $value = 'Create-Job-Subscriptions';
                        break;
                    case 0x0018:
                        $value = 'Get-Subscription-Attributes';
                        break;
                    case 0x0019:
                        $value = 'Get-Subscriptions';
                        break;
                    case 0x001A:
                        $value = 'Renew-Subscription';
                        break;
                    case 0x001B:
                        $value = 'Cancel-Subscription';
                        break;
                    case 0x001C:
                        $value = 'Get-Notifications';
                        break;
                    case 0x001D:
                        $value = sprintf('Unknown (reserved IETF "operations"): 0x%x', \ord($value));
                        break;
                    case 0x001E:
                        $value = sprintf('Unknown (reserved IETF "operations"): 0x%x', \ord($value));
                        break;
                    case 0x001F:
                        $value = sprintf('Unknown (reserved IETF "operations"): 0x%x', \ord($value));
                        break;
                    case 0x0020:
                        $value = sprintf('Unknown (reserved IETF "operations"): 0x%x', \ord($value));
                        break;
                    case 0x0021:
                        $value = sprintf('Unknown (reserved IETF "operations"): 0x%x', \ord($value));
                        break;
                    case 0x0022:
                        $value = 'Enable-Printer';
                        break;
                    case 0x0023:
                        $value = 'Disable-Printer';
                        break;
                    case 0x0024:
                        $value = 'Pause-Printer-After-Current-Job';
                        break;
                    case 0x0025:
                        $value = 'Hold-New-Jobs';
                        break;
                    case 0x0026:
                        $value = 'Release-Held-New-Jobs';
                        break;
                    case 0x0027:
                        $value = 'Deactivate-Printer';
                        break;
                    case 0x0028:
                        $value = 'Activate-Printer';
                        break;
                    case 0x0029:
                        $value = 'Restart-Printer';
                        break;
                    case 0x002A:
                        $value = 'Shutdown-Printer';
                        break;
                    case 0x002B:
                        $value = 'Startup-Printer';
                        break;
                }
                if ($value_parsed > 0x002B && $value_parsed <= 0x3FFF) {
                    $value = sprintf('Unknown(IETF standards track operations reserved): 0x%x', $value_parsed);
                } elseif ($value_parsed >= 0x4000 && $value_parsed <= 0x8FFF) {
                    if (method_exists($this, '_getEnumVendorExtensions')) {
                        $value = $this->_getEnumVendorExtensions($value_parsed);
                    } else {
                        $value = sprintf('Unknown(Vendor extension for operations): 0x%x', $value_parsed);
                    }
                } elseif ($value_parsed > 0x8FFF) {
                    $value = sprintf('Unknown operation (should not exists): 0x%x', $value_parsed);
                }

                break;
            case 'finishings':
            case 'finishings-default':
            case 'finishings-supported':
                switch ($value_parsed) {
                    case 3:
                        $value = 'none';
                        break;
                    case 4:
                        $value = 'staple';
                        break;
                    case 5:
                        $value = 'punch';
                        break;
                    case 6:
                        $value = 'cover';
                        break;
                    case 7:
                        $value = 'bind';
                        break;
                    case 8:
                        $value = 'saddle-stitch';
                        break;
                    case 9:
                        $value = 'edge-stitch';
                        break;
                    case 20:
                        $value = 'staple-top-left';
                        break;
                    case 21:
                        $value = 'staple-bottom-left';
                        break;
                    case 22:
                        $value = 'staple-top-right';
                        break;
                    case 23:
                        $value = 'staple-bottom-right';
                        break;
                    case 24:
                        $value = 'edge-stitch-left';
                        break;
                    case 25:
                        $value = 'edge-stitch-top';
                        break;
                    case 26:
                        $value = 'edge-stitch-right';
                        break;
                    case 27:
                        $value = 'edge-stitch-bottom';
                        break;
                    case 28:
                        $value = 'staple-dual-left';
                        break;
                    case 29:
                        $value = 'staple-dual-top';
                        break;
                    case 30:
                        $value = 'staple-dual-right';
                        break;
                    case 31:
                        $value = 'staple-dual-bottom';
                        break;
                }
                if ($value_parsed > 31) {
                    $value = sprintf('Unknown(IETF standards track "finishing" reserved): 0x%x', $value_parsed);
                }
                break;

            case 'orientation-requested':
            case 'orientation-requested-supported':
            case 'orientation-requested-default':
                switch ($value_parsed) {
                    case 0x03:
                        $value = 'portrait';
                        break;
                    case 0x04:
                        $value = 'landscape';
                        break;
                    case 0x05:
                        $value = 'reverse-landscape';
                        break;
                    case 0x06:
                        $value = 'reverse-portrait';
                        break;
                }
                if ($value_parsed > 0x06) {
                    $value = sprintf('Unknown(IETF standards track "orientation" reserved): 0x%x', $value_parsed);
                }
                break;

            default:
                break;
        }

        return $value;
    }

    public function validateJob()
    {
        $this->jobs = array_merge($this->jobs, ['']);
        $this->jobs_uri = array_merge($this->jobs_uri, ['']);

        $this->serveroutput->response = '';

        $this->_putDebug(sprintf("*************************\nDate: %s\n*************************\n\n",
            date('Y-m-d H:i:s')));

        $this->_putDebug(_("Validate Job\n"), 2);

        if (!isset($this->setup->charset)) {
            $this->setCharset();
        }
        if (!isset($this->setup->datatype)) {
            $this->setBinary();
        }

        if (!isset($this->setup->uri)) {
            $this->getPrinters();
            unset($this->jobs[\count($this->jobs) - 1], $this->jobs_uri[\count($this->jobs_uri) - 1], $this->status[\count($this->status) - 1]);

            if (array_key_exists(0, $this->available_printers)) {
                $this->setPrinterURI($this->available_printers[0]);
            } else {
                trigger_error(_('_stringJob: Printer URI is not set: die'), E_USER_WARNING);
                $this->_putDebug(_("_stringJob: Printer URI is not set: die\n"), 3);
                $this->_errorLog(' Printer URI is not set, die', 2);

                return false;
            }
        }

        if (!isset($this->meta->copies)) {
            $this->setCopies();
        }
        if (!isset($this->setup->copies)) {
            $this->setCopies();
        }

        if (!isset($this->setup->language)) {
            $this->setLanguage();
        }

        if (!isset($this->setup->mime_media_type)) {
            $this->setMimeMediaType();
        }
        if ($this->setup->datatype !== 'TEXT') {
            unset($this->setup->mime_media_type);
        }

        if (!isset($this->setup->jobname)) {
            if (is_readable($this->data)) {
                $this->setJobName(basename($this->data), true);
            } else {
                $this->setJobName();
            }
        }
        unset($this->setup->jobname);

        if (!isset($this->meta->username)) {
            $this->setUserName();
        }

        if (!isset($this->meta->fidelity)) {
            $this->meta->fidelity = '';
        }

        if (!isset($this->meta->document_name)) {
            $this->meta->document_name = '';
        }

        if (!isset($this->meta->sides)) {
            $this->meta->sides = '';
        }

        if (!isset($this->meta->page_ranges)) {
            $this->meta->page_ranges = '';
        }

        $jobattributes = '';
        $operationattributes = '';
        $printerattributes = '';
        $this->_buildValues($operationattributes, $jobattributes, $printerattributes);

        $this->_setOperationId();

        $this->stringjob = \chr(0x01).\chr(0x01) // 1.1  | version-number
            .\chr(0x00).\chr(0x04) // Validate-Job | operation-id
            .$this->meta->operation_id //           request-id
            .\chr(0x01) // start operation-attributes | operation-attributes-tag
            .$this->meta->charset
            .$this->meta->language
            .$this->meta->printer_uri
            .$this->meta->username
            .$this->meta->jobname
            .$this->meta->fidelity
            .$this->meta->document_name
            .$this->meta->mime_media_type
            .$operationattributes
            .\chr(0x02) // start job-attributes | job-attributes-tag
            .$this->meta->copies
            .$this->meta->sides
            .$this->meta->page_ranges
            .$jobattributes
            .\chr(0x03); // end-of-attributes | end-of-attributes-tag

        $this->_putDebug(sprintf(_("String sent to the server is:\n%s\n"), $this->stringjob));

        $this->output = $this->stringjob;

        $post_values = [
            'Content-Type' => 'application/ipp',
            'Data' => $this->output,
        ];

        if ($this->_sendHttp($post_values, $this->paths['printers']) && $this->_parseServerOutput()) {
            $this->_parseAttributes();
        }

        if (isset($this->serveroutput, $this->serveroutput->status)) {
            $this->status = array_merge($this->status, [$this->serveroutput->status]);

            if ($this->serveroutput->status === 'successful-ok') {
                $this->_errorLog('validate job: '.$this->serveroutput->status, 3);
            } else {
                $this->_errorLog('validate job: '.$this->serveroutput->status, 1);
            }

            return $this->serveroutput->status;
        }

        $this->status = array_merge($this->status, ['OPERATION FAILED']);
        $this->_errorLog('validate job : OPERATION FAILED', 3);

        return false;
    }

    public function getPrinters()
    {
        // placeholder for vendor extension operation (getAvailablePrinters for CUPS)
        $this->jobs = array_merge($this->jobs, ['']);
        $this->jobs_uri = array_merge($this->jobs_uri, ['']);
        $this->status = array_merge($this->status, ['']);
    }

    protected function _parseAttributes()
    {
        $k = -1;
        $l = 0;
        foreach ($this->serveroutput->response as $i => $iValue) {
            for ($j = 0; $j < (\count($this->serveroutput->response[$i]) - 1); ++$j) {
                if (empty($this->serveroutput->response[$i][$j]['name'])) {
                    ++$l;
                    $this->parsed[$k][$l] = $this->serveroutput->response[$i][$j]['value'];
                } else {
                    ++$k;
                    $l = 0;
                    $this->parsed[$k]['range'] = $this->serveroutput->response[$i]['attributes'];
                    $this->parsed[$k]['name'] = $this->serveroutput->response[$i][$j]['name'];
                    $this->parsed[$k]['type'] = $this->serveroutput->response[$i][$j]['type'];
                    $this->parsed[$k][$l] = $this->serveroutput->response[$i][$j]['value'];
                }
            }
        }
        $this->serveroutput->response = [];
        $this->attributes = new \stdClass();
        foreach ($this->parsed as $i => $iValue) {
            $name = $this->parsed[$i]['name'];
            $php_name = str_replace('-', '_', $name);
            $type = $this->parsed[$i]['type'];
            $range = $this->parsed[$i]['range'];
            $this->attributes->$php_name = new \stdClass();
            $this->attributes->$php_name->_type = $type;
            $this->attributes->$php_name->_range = $range;
            for ($j = 0; $j < (\count($this->parsed[$i]) - 3); ++$j) {
                $value = $this->parsed[$i][$j];
                $index = '_value'.$j;
                $this->attributes->$php_name->$index = $value;
            }
        }

        $this->parsed = [];
    }

    public function getPrinterAttributes()
    {
        $this->jobs = array_merge($this->jobs, ['']);
        $this->jobs_uri = array_merge($this->jobs_uri, ['']);

        $jobattributes = '';
        $operationattributes = '';
        $this->_buildValues($operationattributes, $jobattributes, $printerattributes);
        $this->_setOperationId();
        $this->parsed = [];
        unset($this->printer_attributes);

        if (!isset($this->setup->uri)) {
            $this->getPrinters();
            unset($this->jobs[\count($this->jobs) - 1], $this->jobs_uri[\count($this->jobs_uri) - 1], $this->status[\count($this->status) - 1]);

            if (array_key_exists(0, $this->available_printers)) {
                $this->setPrinterURI($this->available_printers[0]);
            } else {
                trigger_error(_('_stringJob: Printer URI is not set: die'), E_USER_WARNING);
                $this->_putDebug(_("_stringJob: Printer URI is not set: die\n"), 3);
                $this->_errorLog(' Printer URI is not set, die', 2);

                return false;
            }
        }

        if (!isset($this->setup->charset)) {
            $this->setCharset();
        }

        if (!isset($this->setup->language)) {
            $this->setLanguage();
        }

        if (!isset($this->meta->username)) {
            $this->setUserName();
        }

        $this->stringjob = \chr(0x01).\chr(0x01) // 1.1  | version-number
            .\chr(0x00).\chr(0x0b) // Print-URI | operation-id
            .$this->meta->operation_id //           request-id
            .\chr(0x01) // start operation-attributes | operation-attributes-tag
            .$this->meta->charset
            .$this->meta->language
            .$this->meta->printer_uri
            .$this->meta->username
            .$printerattributes
            .\chr(0x03); // end-of-attributes | end-of-attributes-tag

        $this->_putDebug(sprintf(_("String sent to the server is:\n%s\n"), $this->stringjob));

        $this->_putDebug(sprintf(_("Getting printer attributes of %s\n"), $this->printer_uri), 2);

        $this->output = $this->stringjob;

        $post_values = [
            'Content-Type' => 'application/ipp',
            'Data' => $this->output,
        ];

        if ($this->_sendHttp($post_values, $this->paths['root']) && $this->_parseServerOutput()) {
            $this->_parsePrinterAttributes();
        }

        $this->attributes = &$this->printer_attributes;

        if (isset($this->serveroutput, $this->serveroutput->status)) {
            $this->status = array_merge($this->status, [$this->serveroutput->status]);

            if ($this->serveroutput->status === 'successful-ok') {
                $this->_errorLog(sprintf(_('getting printer attributes of %s: %s'), $this->printer_uri,
                    $this->serveroutput->status), 3);
            } else {
                $this->_errorLog(sprintf(_('getting printer attributes of %s: %s'), $this->printer_uri,
                    $this->serveroutput->status), 1);
            }

            return $this->serveroutput->status;
        }

        $this->status = array_merge($this->status, ['OPERATION FAILED']);
        $this->_errorLog(date('Y-m-d H:i:s : ')
            .basename($_SERVER['PHP_SELF'])
            .sprintf(_("getting printer's attributes of %s : OPERATION FAILED"),
                $this->printer_uri), 3);

        return false;
    }

    protected function _parsePrinterAttributes()
    {
        //if (!preg_match('#successful#',$this->serveroutput->status))
        //   return false;

        $k = -1;
        $l = 0;
        foreach ($this->serveroutput->response as $i => $iValue) {
            for ($j = 0; $j < (\count($this->serveroutput->response[$i]) - 1); ++$j) {
                if (empty($this->serveroutput->response[$i][$j]['name'])) {
                    ++$l;
                    $this->parsed[$k][$l] = $this->serveroutput->response[$i][$j]['value'];
                } else {
                    ++$k;
                    $l = 0;
                    $this->parsed[$k]['range'] = $this->serveroutput->response[$i]['attributes'];
                    $this->parsed[$k]['name'] = $this->serveroutput->response[$i][$j]['name'];
                    $this->parsed[$k]['type'] = $this->serveroutput->response[$i][$j]['type'];
                    $this->parsed[$k][$l] = $this->serveroutput->response[$i][$j]['value'];
                }
            }
        }
        $this->serveroutput->response = [];

        $this->printer_attributes = new \stdClass();
        foreach ($this->parsed as $i => $iValue) {
            $name = $this->parsed[$i]['name'];
            $php_name = str_replace('-', '_', $name);
            $type = $this->parsed[$i]['type'];
            $range = $this->parsed[$i]['range'];
            $this->printer_attributes->$php_name = new \stdClass();
            $this->printer_attributes->$php_name->_type = $type;
            $this->printer_attributes->$php_name->_range = $range;
            for ($j = 0; $j < (\count($this->parsed[$i]) - 3); ++$j) {
                $value = $this->parsed[$i][$j];
                $index = '_value'.$j;
                $this->printer_attributes->$php_name->$index = $value;
            }
        }

        $this->parsed = [];
    }

    public function getJobs($my_jobs = true, $limit = 0, $which_jobs = 'not-completed', $subset = false)
    {
        $this->jobs = array_merge($this->jobs, ['']);
        $this->jobs_uri = array_merge($this->jobs_uri, ['']);

        $this->_setOperationId();
        $this->parsed = [];
        unset($this->printer_attributes);

        if (!isset($this->setup->uri)) {
            $this->getPrinters();
            unset($this->jobs[\count($this->jobs) - 1], $this->jobs_uri[\count($this->jobs_uri) - 1], $this->status[\count($this->status) - 1]);

            if (array_key_exists(0, $this->available_printers)) {
                $this->setPrinterURI($this->available_printers[0]);
            } else {
                trigger_error(_('getJobs: Printer URI is not set: die'), E_USER_WARNING);
                $this->_putDebug(_("_stringJob: Printer URI is not set: die\n"), 3);
                $this->_errorLog('getJobs: Printer URI is not set, die', 2);

                return false;
            }
        }

        if (!isset($this->setup->charset)) {
            $this->setCharset();
        }

        if (!isset($this->setup->language)) {
            $this->setLanguage();
        }

        if (!isset($this->meta->username)) {
            $this->setUserName();
        }

        if ($limit) {
            $limit = $this->_integerBuild($limit);
            $this->meta->limit = \chr(0x21) // integer
                .$this->_giveMeStringLength('limit')
                .'limit'
                .$this->_giveMeStringLength($limit)
                .$limit;
        } else {
            $this->meta->limit = '';
        }

        $this->meta->which_jobs = '';
        if ($which_jobs === 'completed') {
            $this->meta->which_jobs = \chr(0x44) // keyword
                .$this->_giveMeStringLength('which-jobs')
                .'which-jobs'
                .$this->_giveMeStringLength($which_jobs)
                .$which_jobs;
        }

        $this->meta->my_jobs = '';
        if ($my_jobs) {
            $this->meta->my_jobs = \chr(0x22) // boolean
                .$this->_giveMeStringLength('my-jobs')
                .'my-jobs'
                .$this->_giveMeStringLength(\chr(0x01))
                .\chr(0x01);
        }

        $this->stringjob = \chr(0x01).\chr(0x01) // 1.1  | version-number
            .\chr(0x00).\chr(0x0A) // Get-Jobs | operation-id
            .$this->meta->operation_id //           request-id
            .\chr(0x01) // start operation-attributes | operation-attributes-tag
            .$this->meta->charset
            .$this->meta->language
            .$this->meta->printer_uri
            .$this->meta->username
            .$this->meta->limit
            .$this->meta->which_jobs
            .$this->meta->my_jobs;
        if ($subset) {
            $this->stringjob .=
                \chr(0x44) // keyword
                .$this->_giveMeStringLength('requested-attributes')
                .'requested-attributes'
                .$this->_giveMeStringLength('job-uri')
                .'job-uri'
                .\chr(0x44) // keyword
                .$this->_giveMeStringLength('')
                .''
                .$this->_giveMeStringLength('job-name')
                .'job-name'
                .\chr(0x44) // keyword
                .$this->_giveMeStringLength('')
                .''
                .$this->_giveMeStringLength('job-state')
                .'job-state'
                .\chr(0x44) // keyword
                .$this->_giveMeStringLength('')
                .''
                .$this->_giveMeStringLength('job-state-reason')
                .'job-state-reason';
        } else { // cups 1.4.4 doesn't return much of anything without this
            $this->stringjob .=
                \chr(0x44) // keyword
                .$this->_giveMeStringLength('requested-attributes')
                .'requested-attributes'
                .$this->_giveMeStringLength('all')
                .'all';
        }
        $this->stringjob .= \chr(0x03); // end-of-attributes | end-of-attributes-tag

        $this->_putDebug(sprintf(_("String sent to the server is:\n%s\n"), $this->stringjob));

        $this->_putDebug(sprintf(_("getting jobs of %s\n"), $this->printer_uri), 2);

        $this->output = $this->stringjob;

        $post_values = [
            'Content-Type' => 'application/ipp',
            'Data' => $this->output,
        ];

        if ($this->_sendHttp($post_values, $this->paths['jobs']) && $this->_parseServerOutput()) {
            $this->_parseJobsAttributes();
        }

        $this->attributes = &$this->jobs_attributes;

        if (isset($this->serveroutput, $this->serveroutput->status)) {
            $this->status = array_merge($this->status, [$this->serveroutput->status]);

            if ($this->serveroutput->status === 'successful-ok') {
                $this->_errorLog(sprintf(_('getting jobs of printer %s: '), $this->printer_uri)
                    .$this->serveroutput->status, 3);
            } else {
                $this->_errorLog(sprintf(_('getting jobs of printer %s: '), $this->printer_uri)
                    .$this->serveroutput->status, 1);
            }

            return $this->serveroutput->status;
        }

        $this->status = array_merge($this->status, ['OPERATION FAILED']);
        $this->_errorLog(date('Y-m-d H:i:s : ')
            .basename($_SERVER['PHP_SELF'])
            .sprintf(_('getting jobs of %s : OPERATION FAILED'),
                $this->printer_uri), 3);

        return false;
    }

    protected function _parseJobsAttributes()
    {
        //if ($this->serveroutput->status != "successful-ok")
        //    return false;

        $job = -1;
        $l = 0;
        foreach ($this->serveroutput->response as $i => $iValue) {
            if ($this->serveroutput->response[$i]['attributes'] === 'job-attributes') {
                ++$job;
            }
            $k = -1;
            for ($j = 0; $j < (\count($this->serveroutput->response[$i]) - 1); ++$j) {
                if (empty($this->serveroutput->response[$i][$j]['name'])) {
                    ++$l;
                    $this->parsed[$job][$k][$l] = $this->serveroutput->response[$i][$j]['value'];
                } else {
                    ++$k;
                    $l = 0;
                    $this->parsed[$job][$k]['range'] = $this->serveroutput->response[$i]['attributes'];
                    $this->parsed[$job][$k]['name'] = $this->serveroutput->response[$i][$j]['name'];
                    $this->parsed[$job][$k]['type'] = $this->serveroutput->response[$i][$j]['type'];
                    $this->parsed[$job][$k][$l] = $this->serveroutput->response[$i][$j]['value'];
                }
            }
        }

        $this->serveroutput->response = [];
        $this->jobs_attributes = new \stdClass();
        foreach ($this->parsed as $job_nbr => $job_nbrValue) {
            $job_index = 'job_'.$job_nbr;
            $this->jobs_attributes->$job_index = new \stdClass();
            foreach ($this->parsed[$job_nbr] as $i => $iValue) {
                $name = $this->parsed[$job_nbr][$i]['name'];
                $php_name = str_replace('-', '_', $name);
                $type = $this->parsed[$job_nbr][$i]['type'];
                $range = $this->parsed[$job_nbr][$i]['range'];
                $this->jobs_attributes->$job_index->$php_name = new \stdClass();
                $this->jobs_attributes->$job_index->$php_name->_type = $type;
                $this->jobs_attributes->$job_index->$php_name->_range = $range;
                for ($j = 0; $j < (\count($this->parsed[$job_nbr][$i]) - 3); ++$j) {
                    // This causes incorrect parsing of integer job attributes.
                    // 2010-08-16
                    // bpkroth
                    //$value = self::_interpretAttribute($name,$type,$this->parsed[$job_nbr][$i][$j]);
                    $value = $this->parsed[$job_nbr][$i][$j];
                    $index = '_value'.$j;
                    $this->jobs_attributes->$job_index->$php_name->$index = $value;
                }
            }
        }

        $this->parsed = [];
    }

    public function getJobAttributes($job_uri, $subset = false, $attributes_group = 'all')
    {
        $this->jobs = array_merge($this->jobs, ['']);
        $this->jobs_uri = array_merge($this->jobs_uri, ['']);

        if (!$job_uri) {
            trigger_error(_('getJobAttributes: Job URI is not set, die.'));

            return false;
        }

        $this->_setOperationId();
        $this->parsed = [];
        unset($this->printer_attributes);

        if (!isset($this->setup->uri)) {
            $this->getPrinters();
            unset($this->jobs[\count($this->jobs) - 1], $this->jobs_uri[\count($this->jobs_uri) - 1], $this->status[\count($this->status) - 1]);

            if (array_key_exists(0, $this->available_printers)) {
                $this->setPrinterURI($this->available_printers[0]);
            } else {
                trigger_error(_('getJobs: Printer URI is not set: die'), E_USER_WARNING);
                $this->_putDebug(_("_stringJob: Printer URI is not set: die\n"), 3);
                $this->_errorLog('getJobs: Printer URI is not set, die', 2);

                return false;
            }
        }

        if (!isset($this->setup->charset)) {
            $this->setCharset();
        }

        if (!isset($this->setup->language)) {
            $this->setLanguage();
        }

        if (!isset($this->meta->username)) {
            $this->setUserName();
        }

        $this->meta->job_uri = \chr(0x45) // URI
            .$this->_giveMeStringLength('job-uri')
            .'job-uri'
            .$this->_giveMeStringLength($job_uri)
            .$job_uri;

        $this->stringjob = \chr(0x01).\chr(0x01) // 1.1  | version-number
            .\chr(0x00).\chr(0x09) // Get-Job-Attributes | operation-id
            .$this->meta->operation_id //           request-id
            .\chr(0x01) // start operation-attributes | operation-attributes-tag
            .$this->meta->charset
            .$this->meta->language
            .$this->meta->job_uri
            .$this->meta->username;
        if ($subset) {
            $this->stringjob .=
                \chr(0x44) // keyword
                .$this->_giveMeStringLength('requested-attributes')
                .'requested-attributes'
                .$this->_giveMeStringLength('job-uri')
                .'job-uri'
                .\chr(0x44) // keyword
                .$this->_giveMeStringLength('')
                .''
                .$this->_giveMeStringLength('job-name')
                .'job-name'
                .\chr(0x44) // keyword
                .$this->_giveMeStringLength('')
                .''
                .$this->_giveMeStringLength('job-state')
                .'job-state'
                .\chr(0x44) // keyword
                .$this->_giveMeStringLength('')
                .''
                .$this->_giveMeStringLength('job-state-reason')
                .'job-state-reason';
        } elseif ($attributes_group) {
            switch ($attributes_group) {
                case 'job-template':
                    break;
                case 'job-description':
                    break;
                case 'all':
                    break;
                default:
                    trigger_error(_('not a valid attribute group: ').$attributes_group);
                    $attributes_group = '';
                    break;
            }
            $this->stringjob .=
                \chr(0x44) // keyword
                .$this->_giveMeStringLength('requested-attributes')
                .'requested-attributes'
                .$this->_giveMeStringLength($attributes_group)
                .$attributes_group;
        }
        $this->stringjob .= \chr(0x03); // end-of-attributes | end-of-attributes-tag

        $this->_putDebug(sprintf(_("String sent to the server is:\n%s\n"), $this->stringjob));

        $this->_putDebug(sprintf(_("getting jobs of %s\n"), $this->printer_uri), 2);

        $this->output = $this->stringjob;

        $post_values = [
            'Content-Type' => 'application/ipp',
            'Data' => $this->output,
        ];

        if ($this->_sendHttp($post_values, $this->paths['jobs']) && $this->_parseServerOutput()) {
            $this->_parseJobAttributes();
        }

        $this->attributes = &$this->job_attributes;

        if (isset($this->serveroutput, $this->serveroutput->status)) {
            $this->status = array_merge($this->status, [$this->serveroutput->status]);

            if ($this->serveroutput->status === 'successful-ok') {
                $this->_errorLog(sprintf(_('getting job attributes for %s: '), $job_uri)
                    .$this->serveroutput->status, 3);
            } else {
                $this->_errorLog(sprintf(_('getting job attributes for %s: '), $job_uri)
                    .$this->serveroutput->status, 1);
            }

            return $this->serveroutput->status;
        }

        $this->status = array_merge($this->status, ['OPERATION FAILED']);
        $this->_errorLog(date('Y-m-d H:i:s : ')
            .basename($_SERVER['PHP_SELF'])
            .sprintf(_('getting jobs attributes of %s : OPERATION FAILED'),
                $job_uri), 3);

        return false;
    }

    public function generateError($error)
    {
        switch ($error) {
            case 'request_body_malformed':
                $this->error_generation->request_body_malformed = \chr(0xFF);
                break;
            default:
                true;
                break;
        }

        trigger_error(sprintf(_('Setting Error %s'), $error));
    }

    public function resetError($error)
    {
        unset($this->error_generation->$error);
        trigger_error(sprintf(_('Reset Error %s'), $error));
    }
}
