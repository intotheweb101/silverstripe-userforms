<?php

/**
 * Allows a user to add a field that can be used to upload a file.
 *
 * @package userforms
 */

class EditableFileField extends EditableFormField {

	private static $singular_name = 'File Upload Field';

	private static $plural_names = 'File Fields';

	private static $db = array(
		'MaxFileSize' => 'Int'
	);

	private static $has_one = array(
		'Folder' => 'Folder' // From CustomFields
	);

	/**
	 * Further limit uploadable file extensions in addition to the restrictions
	 * imposed by the File.allowed_extensions global configuration.
	 * @config
	 */
	private static $allowed_extensions_blacklist = array(
		'htm', 'html', 'xhtml', 'swf', 'xml'
	);

	/**
	 * @return FieldList
	 */
	public function getCMSFields() {
		$fields = parent::getCMSFields();

		$fields->addFieldToTab(
			'Root.Main',
			TreeDropdownField::create(
				'FolderID',
				_t('EditableUploadField.SELECTUPLOADFOLDER', 'Select upload folder'),
				'Folder'
			)
		);

		$fields->addFieldToTab("Root.Main", new LiteralField("FileUploadWarning",
				"<p class=\"message notice\">" . _t("UserDefinedForm.FileUploadWarning",
				"Files uploaded through this field could be publicly accessible if the exact URL is known")
				. "</p>"), "Type");

		$fields->addFieldToTab('Root.Main',
			$numericField = new NumericField('MaxFileSize',
				_t('EditableUploadField.MaxFileSize',
				'Enter in the maximum file size in MB for this upload field.'))
		);
		$maxUpload = File::ini2bytes(ini_get('upload_max_filesize'));
		$maxPost= File::ini2bytes(ini_get('post_max_size'));
		$maxSizeBytes = min($maxUpload, $maxPost);
		$numericField->setDescription(
			sprintf(
				_t('EditableUploadField.MaximumFileSize',
				'Note: Maximum php allowed size is %s MB'),
				(round($maxSizeBytes / 1024.0, 1) /1024)
			)
		);

		return $fields;
	}

	public function getFormField() {
		$field = FileField::create($this->Name, $this->EscapedTitle)
			->setFieldHolderTemplate('UserFormsField_holder')
			->setTemplate('UserFormsFileField');

		$field->getValidator()->setAllowedExtensions(
			array_diff(
				// filter out '' since this would be a regex problem on JS end
				array_filter(Config::inst()->get('File', 'allowed_extensions')),
				$this->config()->allowed_extensions_blacklist
			)
		);

		$folder = $this->Folder();
		if($folder && $folder->exists()) {
			$field->setFolderName(
				preg_replace("/^assets\//","", $folder->Filename)
			);
		}

		$this->doUpdateFormField($field);

		return $field;
	}

	/**
	 * Validate if the file is over a certain size
	 *
	 * @return boolean
	 */
	public function validateField($data, $form) {
		if ($this->MaxFileSize) {
			foreach ($data as $file) {
				if (isset($file['size'])) {
					if (($this->MaxFileSize * 1024 * 1024) < $file['size']) {
						$form->addErrorMessage($this->Name,
							sprintf(
								_t('EditableUploadField.FileSizeExceded',
									'File size can not be over %s mb.'),
								$this->MaxFileSize),
							'error',
							false
						);
					}
				}
			}
		}
		return true;
	}


	/**
	 * Return the value for the database, link to the file is stored as a
	 * relation so value for the field can be null.
	 *
	 * @return string
	 */
	public function getValueFromData() {
		return null;
	}

	public function getSubmittedFormField() {
		return new SubmittedFileField();
	}


	public function migrateSettings($data) {
		// Migrate 'Folder' setting to 'FolderID'
		if(isset($data['Folder'])) {
			$this->FolderID = $data['Folder'];
			unset($data['Folder']);
		}

		parent::migrateSettings($data);
	}
}
