<?php

namespace Craft;

class RedirectManagerService extends BaseApplicationComponent
{
	protected $redirectRecord;


	public function __construct($redirectRecord = null)
	{
		$this->redirectRecord = $redirectRecord;
		if (is_null($this->redirectRecord)) {
			$this->redirectRecord = RedirectManagerRecord::model();
		}
	}

	public function processRedirect($uri)
	{
		$records = $this->getAllRedirects();
		$doRedirect = false;

		foreach($records as $record)
		{
			$record = $record->attributes;

			// trim to tolerate whitespace in user entry
			$record['uri'] = trim($record['uri']);

			// type of match. 3 possibilities:
			// standard match (no *, no initial and final #) - regex_match = false
			// regex match (initial and final # (may also contain *)) - regex_match = true
			// wildcard match (no initial and final #, but does have *) - regex_match = true
			$regex_match = false;
			if(preg_match("/^#(.+)#$/", $record['uri'], $matches)) {
				// all set to use the regex
				$regex_match = true;
			} elseif (strpos($record['uri'], "*")) {
				// not necessary to replace / with \/ here, but no harm to it either
				$record['uri'] = "#^".str_replace(array("*","/"), array("(.*)", "\/"), $record['uri']).'#';
				$regex_match = true;
			}
			if ($regex_match) {
				if(preg_match($record['uri'], $uri)){
					$redirectLocation = preg_replace($record['uri'], $record['location'], $uri);
					break;
				}
			} else {
				// Standard match
				if ($record['uri'] == $uri)
				{
					$redirectLocation = $record['location'];
					break;
				}
			}
		}
		return (isset($redirectLocation)) ? array("url" => ( strpos($record['location'], "http") === 0 ) ? $redirectLocation : UrlHelper::getSiteUrl($redirectLocation), "type" => $record['type']) : false;
	}

	public function processCSV($csv)
	{
		if ($csv == '') {
			throw new Exception('The CSV data appears to be missing.');
		}
		// Replace newlines with commas
		$results = preg_replace("/[\n\r]+/", ',', $csv);

		// Parse the CSV file and split into an associative array
		$results = array_chunk(str_getcsv($results, ','), 3);

		return $results;
	}

	public function newRedirect($attributes = array())
	{
		$model = new RedirectManagerModel();
		$model->setAttributes($attributes);

		return $model;
	}

	public function getAllRedirects()
	{
		$records = $this->redirectRecord->findAll(array('order'=>'id'));
		return RedirectManagerModel::populateModels($records, 'id');
	}

	public function getRedirectById($id)
	{
		if ($record = $this->redirectRecord->findByPk($id)) {
			return RedirectManagerModel::populateModel($record);
		}
	}

	public function saveRedirect(RedirectManagerModel &$model)
	{
		if ($id = $model->getAttribute('id')) {
			if (null === ($record = $this->redirectRecord->findByPk($id))) {
				throw new Exception(Craft::t('Can\'t find a redirect with ID "{id}"', array('id' => $id)));
			}
		} else {
			$record = $this->redirectRecord->create();
		}

		$record->setAttributes($model->getAttributes());
		if ($record->save()) {
			// update id on model (for new records)
			$model->setAttribute('id', $record->getAttribute('id'));

			return true;
		} else {
			$model->addErrors($record->getErrors());

			return false;
		}
	}

    public function saveRedirects($arrRedirects)
    {
        // Loop through and save (track the outcome for each record)
        $arrResult = [
            'success' => [],
            'failed' => []
        ];

        foreach ($arrRedirects as $redirect) {

            $model = $this->newRedirect([
                'uri' => $redirect[0],
                'location' => $redirect[1],
                'type' => $redirect[2]
            ]);

            if ($this->saveRedirect($model)) {
                $arrResult['success'][] = $redirect;
            } else {
                if ($this->recordExistsAndShouldOverwrite($model)) {

                    $record = $this->findByAttributes(['uri' => $model->getAttribute('uri')]);

                    $model->setAttribute('id', $record->getAttribute('id'));

                    if ($this->saveRedirect($model)) {
                        $arrResult['success'][] = $redirect;
                    } else {
                        $redirect['failureMessageArray'] = $model->getErrors();
                        $arrResult['failed'][] = $redirect;
                    }
                } else {
                    $redirect['failureMessageArray'] = $model->getErrors();
                    $arrResult['failed'][] = $redirect;
                }
            }


        }

        return $arrResult;

    }

	public function deleteRedirectById($id)
	{
		return $this->redirectRecord->deleteByPk($id);
	}

    public function findByAttributes($attributes,$condition='',$params=array())
    {
        return $this->redirectRecord->findByAttributes($attributes, $condition, $params);
    }

    /**
     * @param $model
     *
     * @return bool
     */
    public function recordExistsAndShouldOverwrite($model)
    {
        return ($model->getError('uri') != null && craft()->request->getPost('redirectRecord')['overwrite']);
    }

    private function _processRegexMatch($uriToMatch, $uri)
	{
		preg_match("/^#(.+)#$/", $uriToMatch, $matches);
		// return ($matches[1] == $uri) ;
	}

	private function _processWildcardMatch($val)
	{

	}
}
