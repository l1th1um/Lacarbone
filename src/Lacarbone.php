<?php

namespace Arozie\Lacarbone;

use Symfony\Component\Process\Process;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Illuminate\Support\Facades\Storage;

class Lacarbone
{
    protected $nodeBinary = 'node';
    protected $template_path = '';
    protected $data = [];
    protected $disk = 'tmp';
    protected $tmp_directory = 'lacarbone';    
    protected $timeout = 60;
    protected $tmp_filename;
        
    public static function template(string $template_path)
    {
        return (new static)->setTemplate($template_path);
    }

    public function setTemplate(string $template_path)
    {
        $this->template_path = $template_path;
        return $this;
    }

    public function data(array $data)
    {
         $this->setData($data);
         return $this;
    }

    public function setData(array $data)
    {
        $this->data = $data;
        return $this;
    }

    public function disk(string $disk)
    {
         $this->setDisk($disk);
         return $this;
    }

    public function setDisk(string $disk)
    {
        $this->disk = $disk;
        return $this;
    }


    public function setNodeBinary(string $nodeBinary)
    {
        $this->nodeBinary = $nodeBinary;

        return $this;
    }

    public function save(string $targetPath)
    {
        $this->cleanupTemporaryFile();
        
        $this->generateJS($this->template_path, $this->data, $targetPath);

        $this->call();        
    }
    
    public function download(string $targetPath)
    {
        $targetPath = Storage::disk($this->disk)->path($this->tmp_directory."/".$targetPath);
        $this->save($targetPath);
        
        return response()->download($targetPath);
    }

    protected function cleanupTemporaryFile()
    {
        Storage::disk($this->disk)->deleteDirectory($this->tmp_directory);
    }

    protected function call()
    {
        $run = $this->run();

        $process = Process::fromShellCommandline($run)->setTimeout($this->timeout);

        $process->run();

        if ($process->isSuccessful()) {
            return rtrim($process->getOutput());
        }

        throw new ProcessFailedException($process);
    }

    protected function generateJS(string $template_path, array $data, string $target_path)
    {
        Storage::disk($this->disk)->makeDirectory($this->tmp_directory);

        $content =  "const fs = require('fs');\n" .
                    "const carbone = require('carbone');\n\n" .
                    "var data = ".json_encode($data, JSON_UNESCAPED_SLASHES|JSON_PRETTY_PRINT).";\n\n" .
                    "carbone.render('". str_replace("\\","/",$template_path)."', data, function(err, result){ \n" .
                    "   fs.writeFileSync('".str_replace("\\","/",$target_path)."', result); \n }); ";

        $tmp_filename = uniqid().".js";

        Storage::disk($this->disk)->put($this->tmp_directory."/".$tmp_filename, $content);
        
        $this->tmp_filename = $tmp_filename;
    }

    protected function run()
    {
        $nodeBinary = $this->nodeBinary ?: 'node';
        $js_path = Storage::disk($this->disk)->path($this->tmp_directory."/".$this->tmp_filename);

        return $this->nodeBinary.' '.str_replace("\\","/",$js_path);
    }    
}
