<?php

class ContentData extends BaseObject
{	
	private $_isPopulated = false;
	private $_isDirty = false;
	
	private $_node;
	private $_property;
	
	private $_mimetype;
	private $_size;
	private $_encoding;	
	private $_url;
	private $_newContent;
	private $_newFileContent;
	
	public function __construct($mimetype=null, $encoding=null)
	{
		$this->_mimetype = $mimetype;
		$this->_encoding = $encoding;
	}	
	
	public function setPropertyDetails($node, $property)
	{
		$this->_node = $node;
		$this->_property = $property;
	}
	
	public function __toString()
	{
		$this->populateContentData();
		return "contentUrl=".$this->_url."|mimetype=".$this->_mimetype."|size=".$this->_size."|encoding=".$this->_encoding;
	}
	
	public function getNode()
	{
		return $this->_node;
	}
	
	public function getProperty()
	{
		return $this->_property;
	}
	
	public function getIsDirty()
	{
		return $this->_isDirty;
	}
	
	public function getMimetype()
	{
		$this->populateContentData();
		return $this->_mimetype;
	}
	
	public function setMimetype($mimetype)
	{
		$this->populateContentData();
		$this->_mimetype = $mimetype;
	}
	
	public function getSize()
	{
		$this->populateContentData();
		return $this->_size;
	}
	
	public function getEncoding()
	{
		$this->populateContentData();
		return $this->_encoding;
	}
	
	public function setEncoding($encoding)
	{
		$this->populateContentData();
		$this->_encoding = $encoding;
	}
	
	public function getUrl()
	{
		// TODO what should be returned if the content has been updated??
		
		$this->populateContentData();
		$result = null;
		if ($this->_url != null)
		{	
			$result = $this->_url."?ticket=".$this->_node->session->ticket;
		}
		return $result;
	}
	
	public function getGuestUrl()
	{
		// TODO what should be returned if the content has been updated??
		
		$this->populateContentData();	
		$result = null;
		if ($this->_url != null)
		{	
			$result = $this->_url."?guest=true";
		}
		return $result;
	}
	
	public function getContent()
	{
		$this->populateContentData();
		
		$result = null;			
		if ($this->_isDirty == true)
		{
			if ($this->_newFileContent != null)
			{
				$handle = fopen($this->_newFileContent, "rb");
				$result = stream_get_contents($handle);
				fclose($handle);	
			}
			else if ($this->_newContent != null)
			{
				$result = $this->_newContent;	
			}	
		}
		else
		{
			if ($this->getUrl() != null)
			{
				$handle = fopen($this->getUrl(), "rb");
				$result = stream_get_contents($handle);
				fclose($handle);	
			}
		}
		return $result;
	}
	
	public function setContent($content)
	{
		$this->populateContentData();
		$this->_isDirty = true;
		$this->_newContent = $content;			
	}
	
	public function writeContentFromFile($fileName)
	{
		$this->populateContentData();
		$this->_isDirty = true;
		$this->_newFileContent = $fileName;		
	}
	
	public function readContentToFile($fileName)
	{
		$handle = fopen($fileName, "wb");
		fwrite($handle, $this->getContent());
		fclose($handle);	
	}
	
	public function onBeforeSave(&$statements, $where)
	{
		if ($this->_isDirty == true)
		{
			// Check mimetype has been set
			if ($this->_mimetype == null)
			{
				throw Exception("A mime type for the content property ".$this->_property." on node ".$this->_node->__toString()." must be set");
			}
			
			// If a file has been specified then read content from there
			$content = null;
			if ($this->_newFileContent != null)
			{
				// Read the content from the file specified
				$handle = fopen($this->_newFileContent, "rb");
				$content = stream_get_contents($handle);
				fclose($handle);	
			}
			else
			{
				$content = $this->_newContent;
			} 
			
			// Add the writeContent statement
			$this->addStatement(
						$statements, 
						"writeContent", 
						array(
							"property" => $this->_property,
							"content" => $content,
							"format" => array(
								"mimetype" => $this->_mimetype,
								"encoding" => $this->_encoding)) + 
							$where); 
		}
	}
	
	public function onAfterSave()
	{
		$this->_isDirty = false;
		$this->_isPopulated = false;
		$this->_mimetype = null;
		$this->__size = null;
		$this->__encoding = null;	
		$this->__url = null;
		$this->__newContent = null;
	}
	
	private function populateContentData()
	{
		if ($this->_isPopulated == false && $this->_node != null && $this->_property != null)
		{
			$result = $this->_node->session->contentService->read( array(
																"items" => array(
																	"nodes" => array(
																		"store" => $this->_node->store->__toArray(),
																		"uuid" => $this->_node->id)),			
																"property" => $this->_property) );
			if (isset($result->content) == true)
			{										
				if (isset($result->content->length) == true)
				{																
					$this->_size = $result->content->length;
				}
				if (isset($result->content->format->mimetype) == true)
				{																
					$this->_mimetype = $result->content->format->mimetype;
				}
				if (isset($result->content->format->encoding) == true)
				{
					$this->_encoding = $result->content->format->encoding;
				}
				if (isset($result->content->url) == true)
				{
					$this->_url = $result->content->url;
				}
			}															
			
			$this->_isPopulated = true;
		}
	}
	
	private function addStatement(&$statements, $statement, $body)
	{		
		$result = array();	
		if (array_key_exists($statement, $statements) == true)	
		{
			$result = $statements[$statement];
		}
		$result[] = $body;
		$statements[$statement] = $result;
	}
}
?>
