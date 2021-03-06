<?php namespace Command;

use Pimple;
use Psr\Log\LoggerInterface;

class Application extends Pimple {

    protected $commands = array();
    protected $commandDescriptions = array();
    
    protected $autoResolveNamespaceSeparator = ':';
    
    protected $environmentSeparator = '@';
    
    protected $autoResolveCommands = false;
    
    protected $environment;
    
    protected $resolvedCommand;
    
    public function setAutoResolveNamespaceSeparator($sep)
    {
        $this->autoResolveNamespaceSeparator = $sep;
    }
    
    public function setAutoResolveCommands($status)
    {
        $this->autoResolveCommands = (bool)$status;
    }

    public function registerCommand($name, $command, $description = '')
    {

        if (!is_string($command))
        {
            $command = function() {return $command;};
        }

        $this->commands[$name] = $command;
        $this->commandDescriptions[$name] = $description;

        return $this;
    }
    
    public function registerCommands(array $commands)
    {

        foreach($commands as $name => $command){

            if(is_array($command)){
                
                if(!isset($command[0]) && $command[1]){
                    throw new Exception("If the command is set as an array, the form must be 'array(\$command,\$description)' for command: [$name]");
                }
                
                list($command, $description) = $command;
            }else{
                $description = '';
            }
            
            $this->registerCommand($name, $command, $description);
        }

        return $this;
    }

    public function getCommand($command)
    {

        if(isset($this->commands[$command]))
        {
            return $this->commands[$command];
        }
        
        if($this->autoResolveCommands) {
            
            $class = str_replace(' ','\\', ucwords(str_replace($this->autoResolveNamespaceSeparator,' ',$command)));

            if(class_exists($class))
            {
                if(!is_subclass_of($class, __NAMESPACE__ . '\\Command'))
                {
                    throw new CommandNotFoundException("Command [$command] must extend " .  __NAMESPACE__ . '\\Command');
                }
                
                return $class;
            }
        }
        
        throw new CommandNotFoundException("Command [$command] does not exist");
    }
    
    /**
     * Allow environment name to be set in the command, eg. command:name
     * 
     * @param type $command
     * @return type
     */
    protected function parseCommandAndEnvironment($command)
    {
        $parts = explode($this->environmentSeparator, $command);
        
        isset($parts[1]) or $parts[1] = null;
        
        return array($parts[0], $parts[1]);
    }
    
    public function getEnvironment()
    {
        return $this->environment;
    }
    
    public function setEnvironment($env)
    {
         $this->environment = $env;
    }
    
    public function setEnvironmentFromArgv()
    {
        if($_SERVER['argc'] < 2){
            throw new CommandNotFoundException("Command not specified");
        }
        
        list(, $environment) =  $this->parseCommandAndEnvironment($_SERVER['argv'][1]);
        
        $this->setEnvironment($environment);
    }

    public function runFromArgv($output = null)
    {
        if($_SERVER['argc'] < 2){
            throw new CommandNotFoundException("Command not specified");
        }
        
        list($command, $environment) =  $this->parseCommandAndEnvironment($_SERVER['argv'][1]);
        
        list($arguments, $options) = Command::parseArgs(array_slice($_SERVER['argv'], 2));
        
        $this->environment or $this->environment = $environment;
        
        $this->run($command, $arguments, $options, $output);
    }

    public function run($command = null, $inputArgs = array(), $inputOptions = array(), LoggerInterface $output = null)
    {
        $resolved = $this->getCommand($command);
        
        if (is_string($resolved))
        {
            $resolved = new $resolved($command, $inputArgs, $inputOptions, $output);
        }
        
        $resolved->setApplication($this);
        
        $resolved->configure();

        $resolved->fire();
    }

}
