<?php

/* This file is part of Jeedom.
 *
 * Jeedom is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * Jeedom is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with Jeedom. If not, see <http://www.gnu.org/licenses/>.
 */

/* * ***************************Includes********************************* */
require_once __DIR__  . '/../../../../core/php/core.inc.php';
require_once __DIR__  . '/../../core/php/hyperionNg.inc.php';

class hyperionNg extends eqLogic {
    /*     * *************************Attributs****************************** */
    
  /*
   * Permet de définir les possibilités de personnalisation du widget (en cas d'utilisation de la fonction 'toHtml' par exemple)
   * Tableau multidimensionnel - exemple: array('custom' => true, 'custom::layout' => false)
	public static $_widgetPossibility = array();
   */
    
    /*     * ***********************Methode static*************************** */
	
	public static function updateServers() {
		$servers = null;
		foreach (self::byType(__CLASS__) as $hyperion) {
			
			if ($hyperion->getIsEnable() == 1) {
				
				$host = $hyperion->getConfiguration('host');
				$port = $hyperion->getConfiguration('port');
				$name = $hyperion->getName();
				$token = $hyperion->getConfiguration('token');
				$servers['servers'][] = array('host' => $host, 'port' => $port, 'name' => $name, 'token' => $token);
			}
		}
		$state = callHyperion('/updateServerList', $servers);
	}
	
	public static function pull() {
		foreach (self::byType(__CLASS__) as $hyperion) {
			
			if ($hyperion->getIsEnable() == 1) {
				
				$hyperion->updateServer();
			}
		}
    }
	
	/*     * ********************************************************************** */
	/*     * ***********************HYPERION MANAGEMENT*************************** */
	
	public static function dependancy_info($_refresh = false) {
		$return = array();
		$return['log'] = log::getPathToLog(__CLASS__ . '_update');
		$return['progress_file'] = jeedom::getTmpFolder(__CLASS__) . '/dependency';
		if (file_exists(jeedom::getTmpFolder(__CLASS__) . '/dependency')) {
            $return['state'] = 'in_progress';
        } else {
			if (is_dir(dirname(__FILE__) . '/../../resources/hyperion/node_modules') && self::compilationOk()) {
				$return['state'] = 'ok';
			}
			else {
				$return['state'] = 'nok';
			}
		}
		return $return;
	}

	public static function dependancy_install() {
		log::remove(__CLASS__ . '_update');
		return array('script' => dirname(__FILE__) . '/../../resources/install_#stype#.sh ' . jeedom::getTmpFolder(__CLASS__) . '/dependency', 'log' => log::getPathToLog(__CLASS__ . '_update'));
	}
	
	public static function compilationOk() {
		if (shell_exec('ls /usr/bin/node 2>/dev/null | wc -l') == 0) {
			return false;
		}
		return true;
	}
	
	public static function deamon_info() {
		$return = array();
		$return['state'] = 'nok';
		$pid_file = jeedom::getTmpFolder(__CLASS__) . '/deamon.pid';
		if (file_exists($pid_file)) {
			if (posix_getsid(trim(file_get_contents($pid_file)))) {
				$return['state'] = 'ok';
			} else {
				shell_exec(system::getCmdSudo() . 'rm -rf ' . $pid_file . ' 2>&1 > /dev/null');
			}
		}
		$return['launchable'] = 'nok';
        $port_server = config::byKey('port_server', __CLASS__);
		if ($port_server == '') {
            $return['launchable_message'] = __('Le port serveur n\'est pas configuré', __FILE__);
		}
		else {
			$return['launchable'] = 'ok';
		}
		return $return;
	}
	
	public static function deamon_start($_debug = false) {
		self::deamon_stop();
		$deamon_info = self::deamon_info();
		if ($deamon_info['launchable'] != 'ok') {
			throw new Exception(__('Veuillez vérifier la configuration', __FILE__));
		}
		$port_server = config::byKey('port_server', __CLASS__);
		$hyperion_path = dirname(__FILE__) . '/../../resources';
		
		$cmd = 'node ' . $hyperion_path . '/hyperion/app.js';
		$cmd .= ' ' . $port_server;
		$cmd .= ' ' . jeedom::getApiKey(__CLASS__);
		$cmd .= ' ' . jeedom::getTmpFolder(__CLASS__) . '/deamon.pid';
		
		log::add(__CLASS__, 'info', 'Lancement démon hyperion : ' . $cmd);
		exec($cmd . ' >> ' . log::getPathToLog(__CLASS__) . ' 2>&1 &');
		$i = 0;
		while ($i < 30) {
			$deamon_info = self::deamon_info();
			if ($deamon_info['state'] == 'ok') {
				break;
			}
			sleep(1);
			$i++;
		}
		if ($i >= 30) {
			log::add(__CLASS__, 'error', 'Impossible de lancer le démon hyperion, relancer le démon en debug et vérifiez la log', 'unableStartDeamon');
			return false;
		}
		message::removeAll(__CLASS__, 'unableStartDeamon');
		log::add(__CLASS__, 'info', 'Démon hyperion lancé');
		return true;
	}
	
	public static function deamon_stop() {
		try {
			$deamon_info = self::deamon_info();
			if ($deamon_info['state'] == 'ok') {
				try {
					callHyperion('/stop', null, 30);
				} catch (Exception $e) {
					
				}
			}
			$pid_file = jeedom::getTmpFolder(__CLASS__) . '/deamon.pid';
			if (file_exists($pid_file)) {
				$pid = intval(trim(file_get_contents($pid_file)));
				system::kill($pid);
			}
		} catch (\Exception $e) {
			
		}
	}


    /*     * *********************Méthodes d'instance************************* */

    public function createOrUpdateCommand() {
        
        /* *****Commandes serveur***** */
		$refresh = $this->getCmd(null, 'refresh');
        if (!is_object($refresh)) {
            $refresh = new hyperionNgCmd();
        }
        $refresh->setName('Rafraichir');
        $refresh->setEqLogic_id($this->getId());
        $refresh->setLogicalId('refresh');
        $refresh->setType('action');
        $refresh->setSubType('other');
		$refresh->setOrder(0);
        $refresh->save();
		
		$cmd_info = $this->getCmd(null,'version');
        if (!is_object($cmd_info)) {
            $cmd_info = new hyperionNgCmd();
        }
        $cmd_info->setName("Version");
		$cmd_info->setEqLogic_id($this->getId());
		$cmd_info->setLogicalId("version");
		$cmd_info->setType('info');
        $cmd_info->setSubType('string');
		$cmd_info->setTemplate('dashboard', 'tile');
        $cmd_info->setTemplate('mobile', 'tile');
        $cmd_info->setDisplay('forceReturnLineAfter', '1');
		$cmd_info->setIsVisible(0);
        $cmd_info->setOrder(1);
        $cmd_info->save();
        
        $cmd_info = $this->getCmd(null,'connected');
        if (!is_object($cmd_info)) {
            $cmd_info = new hyperionNgCmd();
        }
        $cmd_info->setName("Connecté");
		$cmd_info->setEqLogic_id($this->getId());
		$cmd_info->setLogicalId("connected");
		$cmd_info->setType('info');
        $cmd_info->setSubType('binary');
        $cmd_info->setOrder(2);
        $cmd_info->save();
        
        $cmd_info = $this->getCmd(null,'authenticated');
        if (!is_object($cmd_info)) {
            $cmd_info = new hyperionNgCmd();
        }
        $cmd_info->setName("Authentifié");
		$cmd_info->setEqLogic_id($this->getId());
		$cmd_info->setLogicalId("authenticated");
		$cmd_info->setType('info');
        $cmd_info->setSubType('binary');
		$cmd_info->setDisplay('forceReturnLineAfter', '1');
        $cmd_info->setOrder(3);
        $cmd_info->save();
        
        $cmd_info = $this->getCmd(null,'server_all_state');
        if (!is_object($cmd_info)) {
            $cmd_info = new hyperionNgCmd();
        }
        $cmd_info->setName("Hyperion");
		$cmd_info->setEqLogic_id($this->getId());
		$cmd_info->setLogicalId("server_all_state");
		$cmd_info->setType('info');
        $cmd_info->setSubType('binary');
        $cmd_info->setIsVisible(0);
		$cmd_info->setOrder(4);
        $cmd_info->save();
        
        $cmd = $this->getCmd(null,'set_all_server');
        if (!is_object($cmd)) {
            $cmd = new hyperionNgCmd();
        }
        $cmd->setName("ALL");
		$cmd->setEqLogic_id($this->getId());
		$cmd->setLogicalId("set_all_server");
		$cmd->setType('action');
        $cmd->setSubType('other');
        $cmd->setTemplate('dashboard', 'binarySwitch');
        $cmd->setTemplate('mobile', 'binarySwitch');
        $cmd->setDisplay('forceReturnLineAfter', '1');
        $cmd->setIsVisible(0);
		$cmd->setOrder(5);
        $cmd->setValue($this->getCmd(null,'server_all_state')->getId());
        $cmd->save();
		
		$cmd_info = $this->getCmd(null,'server_leddevice_state');
        if (!is_object($cmd_info)) {
            $cmd_info = new hyperionNgCmd();
        }
        $cmd_info->setName("Led device");
		$cmd_info->setEqLogic_id($this->getId());
		$cmd_info->setLogicalId("server_leddevice_state");
		$cmd_info->setType('info');
        $cmd_info->setSubType('binary');
        $cmd_info->setIsVisible(0);
		$cmd_info->setOrder(6);
        $cmd_info->save();
        
        $cmd = $this->getCmd(null,'set_leddevice_server');
        if (!is_object($cmd)) {
            $cmd = new hyperionNgCmd();
        }
        $cmd->setName("LEDDEVICE");
		$cmd->setEqLogic_id($this->getId());
		$cmd->setLogicalId("set_leddevice_server");
		$cmd->setType('action');
        $cmd->setSubType('other');
        $cmd->setTemplate('dashboard', 'binarySwitch');
        $cmd->setTemplate('mobile', 'binarySwitch');
        $cmd->setDisplay('forceReturnLineAfter', '1');
        $cmd->setIsVisible(0);
		$cmd->setOrder(7);
        $cmd->setValue($this->getCmd(null,'server_leddevice_state')->getId());
        $cmd->save();
		
		$cmd_info = $this->getCmd(null,'server_v4l_state');
        if (!is_object($cmd_info)) {
            $cmd_info = new hyperionNgCmd();
        }
        $cmd_info->setName("V4L capture");
		$cmd_info->setEqLogic_id($this->getId());
		$cmd_info->setLogicalId("server_v4l_state");
		$cmd_info->setType('info');
        $cmd_info->setSubType('binary');
        $cmd_info->setIsVisible(0);
		$cmd_info->setOrder(8);
        $cmd_info->save();
        
        $cmd = $this->getCmd(null,'set_v4l_server');
        if (!is_object($cmd)) {
            $cmd = new hyperionNgCmd();
        }
        $cmd->setName("V4L");
		$cmd->setEqLogic_id($this->getId());
		$cmd->setLogicalId("set_v4l_server");
		$cmd->setType('action');
        $cmd->setSubType('other');
        $cmd->setTemplate('dashboard', 'binarySwitch');
        $cmd->setTemplate('mobile', 'binarySwitch');
        $cmd->setDisplay('forceReturnLineAfter', '1');
        $cmd->setIsVisible(0);
		$cmd->setOrder(9);
        $cmd->setValue($this->getCmd(null,'server_v4l_state')->getId());
        $cmd->save();
		
		$cmd_info = $this->getCmd(null,'server_grabber_state');
        if (!is_object($cmd_info)) {
            $cmd_info = new hyperionNgCmd();
        }
        $cmd_info->setName("Platform capture");
		$cmd_info->setEqLogic_id($this->getId());
		$cmd_info->setLogicalId("server_grabber_state");
		$cmd_info->setType('info');
        $cmd_info->setSubType('binary');
        $cmd_info->setIsVisible(0);
		$cmd_info->setOrder(10);
        $cmd_info->save();
        
        $cmd = $this->getCmd(null,'set_grabber_server');
        if (!is_object($cmd)) {
            $cmd = new hyperionNgCmd();
        }
        $cmd->setName("GRABBER");
		$cmd->setEqLogic_id($this->getId());
		$cmd->setLogicalId("set_grabber_server");
		$cmd->setType('action');
        $cmd->setSubType('other');
        $cmd->setTemplate('dashboard', 'binarySwitch');
        $cmd->setTemplate('mobile', 'binarySwitch');
        $cmd->setDisplay('forceReturnLineAfter', '1');
        $cmd->setIsVisible(0);
		$cmd->setOrder(11);
        $cmd->setValue($this->getCmd(null,'server_grabber_state')->getId());
        $cmd->save();
        
        $cmd_info = $this->getCmd(null,'instance');
        if (!is_object($cmd_info)) {
            $cmd_info = new hyperionNgCmd();
        }
        $cmd_info->setName("Instance");
		$cmd_info->setEqLogic_id($this->getId());
		$cmd_info->setLogicalId("instance");
		$cmd_info->setType('info');
        $cmd_info->setSubType('string');
        $cmd_info->setIsVisible(0);
		$cmd_info->setOrder(12);
        $cmd_info->save();
        
        $cmd = $this->getCmd(null,'liste_instances');
        if (!is_object($cmd)) {
            $cmd = new hyperionNgCmd();
        }
        $cmd->setName("Liste instances");
		$cmd->setEqLogic_id($this->getId());
		$cmd->setLogicalId("liste_instances");
		$cmd->setType('action');
        $cmd->setSubType('select');
		$cmd->setIsVisible(0);
        $cmd->setOrder(13);
        $cmd->setValue($this->getCmd(null,'instance')->getId());
        $cmd->save();
        
        /* *****Commandes instances***** */
        $cmd_info = $this->getCmd(null,'started_instance');
        if (!is_object($cmd_info)) {
            $cmd_info = new hyperionNgCmd();
        }
        $cmd_info->setName("Etat instance");
		$cmd_info->setEqLogic_id($this->getId());
		$cmd_info->setLogicalId("started_instance");
		$cmd_info->setType('info');
        $cmd_info->setSubType('binary');
        $cmd_info->setIsVisible(0);
		$cmd_info->setOrder(14);
        $cmd_info->save();
        
        $cmd = $this->getCmd(null,'start_instance');
        if (!is_object($cmd)) {
            $cmd = new hyperionNgCmd();
        }
        $cmd->setName("Démarrer");
		$cmd->setEqLogic_id($this->getId());
		$cmd->setLogicalId("start_instance");
		$cmd->setType('action');
        $cmd->setSubType('other');
        $cmd->setTemplate('dashboard', 'binarySwitch');
        $cmd->setTemplate('mobile', 'binarySwitch');
        $cmd->setDisplay('forceReturnLineAfter', '1');
        $cmd->setIsVisible(0);
		$cmd->setOrder(15);
        $cmd->setValue($this->getCmd(null,'started_instance')->getId());
        $cmd->save();
        
        $cmd_info = $this->getCmd(null,'instance_connected');
        if (!is_object($cmd_info)) {
            $cmd_info = new hyperionNgCmd();
        }
        $cmd_info->setName("Instance connecté");
		$cmd_info->setEqLogic_id($this->getId());
		$cmd_info->setLogicalId("instance_connected");
		$cmd_info->setType('info');
        $cmd_info->setSubType('binary');
        $cmd_info->setOrder(16);
        $cmd_info->setIsVisible(0);
        $cmd_info->save();
        
        $cmd_info = $this->getCmd(null,'instance_authenticated');
        if (!is_object($cmd_info)) {
            $cmd_info = new hyperionNgCmd();
        }
        $cmd_info->setName("Instance authentifié");
		$cmd_info->setEqLogic_id($this->getId());
		$cmd_info->setLogicalId("instance_authenticated");
		$cmd_info->setType('info');
        $cmd_info->setSubType('binary');
		$cmd_info->setDisplay('forceReturnLineAfter', '1');
        $cmd_info->setOrder(17);
        $cmd_info->setIsVisible(0);
        $cmd_info->save();
		
		$cmd_info = $this->getCmd(null,'priorities_autoselect');
        if (!is_object($cmd_info)) {
            $cmd_info = new hyperionNgCmd();
        }
        $cmd_info->setName("Sélection source");
		$cmd_info->setEqLogic_id($this->getId());
		$cmd_info->setLogicalId("priorities_autoselect");
		$cmd_info->setType('info');
        $cmd_info->setSubType('string');
		$cmd_info->setTemplate('dashboard', 'tile');
        $cmd_info->setTemplate('mobile', 'tile');
        $cmd_info->setIsVisible(0);
		$cmd_info->setOrder(18);
        $cmd_info->save();
		
		$cmd = $this->getCmd(null,'source_autoselect');
        if (!is_object($cmd)) {
            $cmd = new hyperionNgCmd();
        }
        $cmd->setName("Sélection source en auto");
        $cmd->setEqLogic_id($this->getId());
        $cmd->setLogicalId("source_autoselect");
        $cmd->setType('action');
        $cmd->setSubType('other');
        $cmd->setDisplay('icon', '<i class="fas fa-play"></i>');
        $cmd->setOrder(19);
		$cmd->setIsVisible(0);
		$cmd->setDisplay('showIconAndNamedashboard', '1');
        $cmd->setDisplay('showIconAndNamemobile', '1');
        $cmd->save();

		$cmd_info = $this->getCmd(null,'effect');
        if (!is_object($cmd_info)) {
            $cmd_info = new hyperionNgCmd();
        }
        $cmd_info->setName("Effet");
		$cmd_info->setEqLogic_id($this->getId());
		$cmd_info->setLogicalId("effect");
		$cmd_info->setType('info');
        $cmd_info->setSubType('string');
        $cmd_info->setIsVisible(0);
		$cmd_info->setOrder(20);
        $cmd_info->save();
        
        $cmd = $this->getCmd(null,'liste_effects');
        if (!is_object($cmd)) {
            $cmd = new hyperionNgCmd();
        }
        $cmd->setName("Liste effets");
		$cmd->setEqLogic_id($this->getId());
		$cmd->setLogicalId("liste_effects");
		$cmd->setType('action');
        $cmd->setSubType('select');
        $cmd->setOrder(21);
		$cmd->setIsVisible(0);
        $cmd->setValue($this->getCmd(null,'effect')->getId());
        $cmd->save();
		
		$cmd = $this->getCmd(null,'effect_cmd');
        if (!is_object($cmd)) {
            $cmd = new hyperionNgCmd();
        }
        $cmd->setName("Activer effet");
        $cmd->setEqLogic_id($this->getId());
        $cmd->setLogicalId("effect_cmd");
        $cmd->setType('action');
        $cmd->setSubType('other');
        $cmd->setDisplay('icon', '<i class="fas fa-play"></i>');
        $cmd->setOrder(22);
		$cmd->setIsVisible(0);
		$cmd->setDisplay('showIconAndNamedashboard', '1');
        $cmd->setDisplay('showIconAndNamemobile', '1');
		$cmd->setDisplay('forceReturnLineAfter', '1');
        $cmd->save();
		
		$cmd = $this->getCmd(null,'color_cmd');
        if (!is_object($cmd)) {
            $cmd = new hyperionNgCmd();
        }
        $cmd->setName("Activer couleur");
        $cmd->setEqLogic_id($this->getId());
        $cmd->setLogicalId("color_cmd");
        $cmd->setType('action');
        $cmd->setSubType('other');
        $cmd->setDisplay('icon', '<i class="fas fa-play"></i>');
        $cmd->setOrder(23);
		$cmd->setIsVisible(0);
		$cmd->setDisplay('showIconAndNamedashboard', '1');
        $cmd->setDisplay('showIconAndNamemobile', '1');
        $cmd->save();
		
		$cmd = $this->getCmd(null,'color');
        if (!is_object($cmd)) {
            $cmd = new hyperionNgCmd();
        }
        $cmd->setName("Couleur");
        $cmd->setEqLogic_id($this->getId());
        $cmd->setLogicalId("color");
        $cmd->setType('action');
        $cmd->setSubType('color');
        $cmd->setDisplay('icon', '<i class="fas fa-play"></i>');
        $cmd->setOrder(24);
		$cmd->setIsVisible(0);
		$cmd->setDisplay('showIconAndNamedashboard', '1');
        $cmd->setDisplay('showIconAndNamemobile', '1');
		$cmd->setDisplay('forceReturnLineAfter', '1');
        $cmd->save();
		
		$cmd = $this->getCmd(null,'clear_cmd');
        if (!is_object($cmd)) {
            $cmd = new hyperionNgCmd();
        }
        $cmd->setName("Désactiver les effets");
        $cmd->setEqLogic_id($this->getId());
        $cmd->setLogicalId("clear_cmd");
        $cmd->setType('action');
        $cmd->setSubType('other');
        $cmd->setDisplay('icon', '<i class="fas fa-play"></i>');
        $cmd->setOrder(25);
		$cmd->setIsVisible(0);
		$cmd->setDisplay('showIconAndNamedashboard', '1');
        $cmd->setDisplay('showIconAndNamemobile', '1');
        $cmd->save();
		
		$cmd_info = $this->getCmd(null,'source');
        if (!is_object($cmd_info)) {
            $cmd_info = new hyperionNgCmd();
        }
        $cmd_info->setName("Source");
		$cmd_info->setEqLogic_id($this->getId());
		$cmd_info->setLogicalId("source");
		$cmd_info->setType('info');
        $cmd_info->setSubType('string');
        $cmd_info->setIsVisible(0);
		$cmd_info->setOrder(26);
        $cmd_info->save();
        
        $cmd = $this->getCmd(null,'liste_sources');
        if (!is_object($cmd)) {
            $cmd = new hyperionNgCmd();
        }
        $cmd->setName("Liste sources");
		$cmd->setEqLogic_id($this->getId());
		$cmd->setLogicalId("liste_sources");
		$cmd->setType('action');
        $cmd->setSubType('select');
        $cmd->setOrder(27);
		$cmd->setIsVisible(0);
        $cmd->setValue($this->getCmd(null,'source')->getId());
        $cmd->save();
        
        /* *****Commandes sources***** */
		$cmd_info = $this->getCmd(null,'source_active');
        if (!is_object($cmd_info)) {
            $cmd_info = new hyperionNgCmd();
        }
        $cmd_info->setName("Source active");
		$cmd_info->setEqLogic_id($this->getId());
		$cmd_info->setLogicalId("source_active");
		$cmd_info->setType('info');
        $cmd_info->setSubType('binary');
		$cmd_info->setOrder(28);
        $cmd_info->setIsVisible(0);
        $cmd_info->save();
		
		$cmd_info = $this->getCmd(null,'source_visible');
        if (!is_object($cmd_info)) {
            $cmd_info = new hyperionNgCmd();
        }
        $cmd_info->setName("Source en cours");
		$cmd_info->setEqLogic_id($this->getId());
		$cmd_info->setLogicalId("source_visible");
		$cmd_info->setType('info');
        $cmd_info->setSubType('binary');
		$cmd_info->setOrder(29);
        $cmd_info->setIsVisible(0);
        $cmd_info->save();
		
		$cmd_info = $this->getCmd(null,'source_priority');
        if (!is_object($cmd_info)) {
            $cmd_info = new hyperionNgCmd();
        }
        $cmd_info->setName("Priorité");
		$cmd_info->setEqLogic_id($this->getId());
		$cmd_info->setLogicalId("source_priority");
		$cmd_info->setType('info');
        $cmd_info->setSubType('string');
		$cmd_info->setTemplate('dashboard', 'tile');
        $cmd_info->setTemplate('mobile', 'tile');
		$cmd_info->setOrder(30);
        $cmd_info->setIsVisible(0);
        $cmd_info->save();
		
		$cmd = $this->getCmd(null,'source_force');
        if (!is_object($cmd)) {
            $cmd = new hyperionNgCmd();
        }
        $cmd->setName("Forcer la source");
        $cmd->setEqLogic_id($this->getId());
        $cmd->setLogicalId("source_force");
        $cmd->setType('action');
        $cmd->setSubType('other');
        $cmd->setDisplay('icon', '<i class="fas fa-play"></i>');
        $cmd->setOrder(31);
		$cmd->setIsVisible(0);
		$cmd->setDisplay('showIconAndNamedashboard', '1');
        $cmd->setDisplay('showIconAndNamemobile', '1');
        $cmd->save();
    }
	
	public function updateServer() {
		
		$host = $this->getConfiguration('host');
		$port = $this->getConfiguration('port');
		$server = callHyperion('/getServerInfo?server=' . $host . ':' . $port);
		$this->updateServerInfo($server);
	}
    
    public function updateServerInfo($data) {

		$this->updateCmdInfo('version', $data["sysInfo"]["hyperion"]["version"]);
		
        $cmd_info = $this->getCmd(null,'connected');
        if (is_object($cmd_info)) {
            if ($cmd_info->formatValue($data["connected"]) != $cmd_info->execCmd()) {
                $cmd_info->setCollectDate('');
                $cmd_info->event($data["connected"]);
            }
        }
        
        $cmd_info = $this->getCmd(null,'authenticated');
        if (is_object($cmd_info)) {
            if ($cmd_info->formatValue($data["authenticated"]) != $cmd_info->execCmd()) {
                $cmd_info->setCollectDate('');
                $cmd_info->event($data["authenticated"]);
            }
        }
        
		$foundInstance = false;
        if ($data["connected"] && $data["authenticated"]) {
            
			$this->updateButtonCmd('set_all_server', 1, 'server_all_state', $data["instances"][0]["components"]["ALL"]["enabled"]);
			if ($data["instances"][0]["components"]["ALL"]["enabled"]) {
				
				$this->updateButtonCmd('set_leddevice_server', 1, 'server_leddevice_state', $data["instances"][0]["components"]["LEDDEVICE"]["enabled"]);
				$this->updateButtonCmd('set_v4l_server', 1, 'server_v4l_state', $data["instances"][0]["components"]["V4L"]["enabled"]);
				$this->updateButtonCmd('set_grabber_server', 1, 'server_grabber_state', $data["instances"][0]["components"]["GRABBER"]["enabled"]);
			}
			else {
				
				$this->updateButtonCmd('set_leddevice_server', 0, 'server_leddevice_state', 0);
				$this->updateButtonCmd('set_v4l_server', 0, 'server_v4l_state', 0);
				$this->updateButtonCmd('set_grabber_server', 0, 'server_grabber_state', 0);
			}
			
			$cmd = $this->getCmd(null,'liste_instances');
			if (is_object($cmd)) {
				$list = "";
				$instances = $data["instances"];
				foreach ($instances as $instanceId => $instance) {
					$list = $list . $separator . $instanceId . '|' . $instance['name'];
					$separator = ';';  
				}
				$cmd->setConfiguration('listValue', $list);
				$cmd->setIsVisible(1);
				$cmd->save();
			}
			
			$instance_info = $this->getCmd(null,'instance');
			if (is_object($instance_info)) {
				$instanceId = $instance_info->execCmd();
				if ($instanceId !== '') {
					foreach ($data["instances"] as $id => $instance) {
				
						if (intval($instanceId) === $id) {
					
							$this->updateInstanceInfo($instance);
							$foundInstance = true;
							break;
						}
					}
				}
			}
        }
        else {
            
			$this->updateButtonCmd('set_all_server', 0, 'server_all_state', 0);
			$this->updateButtonCmd('set_leddevice_server', 0, 'server_leddevice_state', 0);
			$this->updateButtonCmd('set_v4l_server', 0, 'server_v4l_state', 0);
			$this->updateButtonCmd('set_grabber_server', 0, 'server_grabber_state', 0);
			
			$cmd = $this->getCmd(null,'liste_instances');
			if (is_object($cmd)) {
				$cmd->setConfiguration('listValue', "");
				$cmd->setIsVisible(0);
				$cmd->save();
			}
		}
		
		if (!$foundInstance) {
			
			$this->saveInstanceListeState('');
			$this->updateInstanceInfo(null);
		}
    }
	
	public function updateButtonCmd($cmd, $visible, $state = '', $value = '') {
		
		$cmd = $this->getCmd(null, $cmd);
        if (is_object($cmd)) {
                
            $cmd->setIsVisible($visible);
            $cmd->save();
        }
		
		if ($state !== '') {
			
			$cmd_info = $this->getCmd(null, $state);
			if (is_object($cmd_info)) {
                
				if ($cmd_info->formatValue($value) != $cmd_info->execCmd()) {
                    
					$cmd_info->setCollectDate('');
					$cmd_info->event($value);
				}
			}
		}
	}
	
	public function updateCmdInfo($state, $value) {
		
        $cmd_info = $this->getCmd(null, $state);
        if (is_object($cmd_info)) {
            
			(isset($value)) ? $visible = 1 : $visible = 0;
			$cmd_info->setIsVisible($visible);
			$cmd_info->save();
			
            if ($cmd_info->formatValue($value) != $cmd_info->execCmd()) {
                    
                $cmd_info->setCollectDate('');
                $cmd_info->event($value);
            }
        }
	}
    
    public function callUpdateInstanceInfo($instanceId) {
        
		$host = $this->getConfiguration('host');
		$port = $this->getConfiguration('port');
        $instance = callHyperion('/getInstanceInfo?server=' . $host . ':' . $port . '&instance=' . $instanceId);
		$this->updateInstanceInfo($instance);
	}
    
	public function updateInstanceInfo($data) {
		
		(isset($data["running"])) ? $visible = 1 : $visible = 0;
		$this->updateButtonCmd('start_instance', $visible, 'started_instance', $data["running"]);
        
		$this->updateCmdInfo('instance_connected', $data["connected"]);
		$this->updateCmdInfo('instance_authenticated', $data["authenticated"]);
		
		if (isset($data["priorities_autoselect"])) {
			($data["priorities_autoselect"] == true) ? $value = "Auto" : $value = "Manu";
		}
		else {
			$value = null;
		}
		$this->updateCmdInfo('priorities_autoselect', $value);
		
		(isset($data)) ? $visible = 1 : $visible = 0;
		$this->updateButtonCmd('source_autoselect', $visible);
		
		$foundSource = false;
		if (isset($data["priorities"])) {
			
			$cmd = $this->getCmd(null,'liste_sources');
			if (is_object($cmd)) {
				$list = "";
				$separator = "";
				$sources = $data["priorities"];
				foreach ($sources as $id => $source) {
					if (isset($source['owner'])) {
						$name = $source['owner'] . ' - ' . $source['origin'];
					}
					else if (isset($source['componentId'])) {
						$name = $source['componentId'] . ' - ' . $source['origin'];
					}
					
					$list = $list . $separator . $source['origin'] . ':' . $source['componentId'] . '|' . $name;
					$separator = ';';  
				}
				$cmd->setConfiguration('listValue', $list);
				$cmd->setIsVisible(1);
				$cmd->save();
			}
			
			$source_info = $this->getCmd(null,'source');
			if (is_object($source_info)) {
				$key = $source_info->execCmd();
				if ($key !== '') {
					foreach ($data["priorities"] as $id => $source) {
				
						if ($key === ($source['origin'] . ':' . $source['componentId'])) {
					
							$this->updateSourceInfo($source);
							$foundSource = true;
							break;
						}
					}
				}
			}
		}
		else {
			
			$cmd = $this->getCmd(null,'liste_sources');
			if (is_object($cmd)) {
				
				$cmd->setConfiguration('listValue', "");
				$cmd->setIsVisible(0);
				$cmd->save();
			}
		}
		
		if (!$foundSource) {
			
			$this->saveSourcesListeState('');
			$this->updateSourceInfo(null);
		}
		
		if (isset($data["effects"])) {
			
			$cmd = $this->getCmd(null,'liste_effects');
			if (is_object($cmd)) {
				$list = "";
				$separator = "";
				$effects = $data["effects"];
				foreach ($effects as $id => $effect) {
					$list = $list . $separator . $id . '|' . $id;
					$separator = ';';
				}
				$cmd->setConfiguration('listValue', $list);
				$cmd->setIsVisible(1);
				$cmd->save();
			}
		}
		else {
			
			$cmd = $this->getCmd(null,'liste_effects');
			if (is_object($cmd)) {
				
				$cmd->setConfiguration('listValue', "");
				$cmd->setIsVisible(0);
				$cmd->save();
			}
		}
		
		(isset($data["effects"])) ? $visible = 1 : $visible = 0;
		$this->updateButtonCmd('effect_cmd', $visible);
		
		(isset($data)) ? $visible = 1 : $visible = 0;
		$this->updateButtonCmd('color', $visible);
		
		(isset($data)) ? $visible = 1 : $visible = 0;
		$this->updateButtonCmd('color_cmd', $visible);
		
		(isset($data)) ? $visible = 1 : $visible = 0;
		$this->updateButtonCmd('clear_cmd', $visible);
    }
	
	public function callUpdateSourceInfo($key) {
        
		$host = $this->getConfiguration('host');
		$port = $this->getConfiguration('port');
		$cmd_info = $this->getCmd(null,'instance');
        if (is_object($cmd_info)) {
			$instanceId = $cmd_info->execCmd();
			$link = str_replace ( ' ', '%20', '/getSourceInfo?server=' . $host . ':' . $port . '&instance=' . $instanceId . '&source=' . $key);
			$source = callHyperion($link);
			$this->updateSourceInfo($source);
		}
	}
	
	public function updateSourceInfo($data) {
		
		$this->updateCmdInfo('source_active', $data["active"]);
		$this->updateCmdInfo('source_visible', $data["visible"]);
		$this->updateCmdInfo('source_priority', $data["priority"]);
		(isset($data)) ? $visible = 1 : $visible = 0;
		$this->updateButtonCmd('source_force', $visible);
	}
    
    public function saveInstanceListeState($state) {
        
        $cmd_info = $this->getCmd(null,'instance');
        if (is_object($cmd_info)) {
            if ($cmd_info->formatValue($state) != $cmd_info->execCmd()) {
                $cmd_info->setCollectDate('');
                $cmd_info->event($state);
            }
        }
    }
    
    public function toggleStateInstance() {
        
        $instance_info = $this->getCmd(null,'instance');
        if (is_object($instance_info)) {
            $instanceId = $instance_info->execCmd();
            $state_info = $this->getCmd(null,'started_instance');
            if (is_object($state_info)) {
                $state = $state_info->execCmd();
                $newState = 1 - $state;
				$host = $this->getConfiguration('host');
				$port = $this->getConfiguration('port');
                callHyperion('/setInstanceState?server=' . $host . ':' . $port . '&instance=' . $instanceId . '&state=' . $newState);
				sleep(5);
                $this->callUpdateInstanceInfo($instanceId);
            }
        }
    }
    
    public function toggleComponent($instanceId, $componentId) {
        
        $instance_info = $this->getCmd(null,'instance');
        if (is_object($instance_info)) {
            
            $updateInstanceId = $instance_info->execCmd();
            
            $component_state = "";
            if ($instanceId < 0) {
            
                $component_state = "server_";
            }
            $component_state = $component_state . strtolower($componentId) . '_state';
            $state_info = $this->getCmd(null,$component_state);
            
            if (is_object($state_info)) {
                $state = $state_info->execCmd();
                $newState = 1 - $state;
				$host = $this->getConfiguration('host');
				$port = $this->getConfiguration('port');
                callHyperion('/setComponentState?server=' . $host . ':' . $port . '&instance=' . $instanceId . '&component=' . $componentId . '&state=' . $newState);
            }
        }
    }
	
	public function setSource($key) {
		
		$host = $this->getConfiguration('host');
		$port = $this->getConfiguration('port');
		$cmd_info = $this->getCmd(null,'instance');
        if (is_object($cmd_info)) {
			$instanceId = $cmd_info->execCmd();
			$link = str_replace ( ' ', '%20', '/setSource?server=' . $host . ':' . $port . '&instance=' . $instanceId . '&source=' . $key);
			callHyperion($link);
			
			sleep(5);
			$this->callUpdateInstanceInfo($instanceId);
		}
	}
	
	public function setEffect($effect) {
		
		$host = $this->getConfiguration('host');
		$port = $this->getConfiguration('port');
		$cmd_info = $this->getCmd(null,'instance');
        if (is_object($cmd_info)) {
			$instanceId = $cmd_info->execCmd();
            $link = str_replace ( ' ', '%20', '/setEffect?server=' . $host . ':' . $port . '&instance=' . $instanceId . '&effect=' . $effect . '&duration=0');
			callHyperion($link);
			
			sleep(5);
			$this->callUpdateInstanceInfo($instanceId);
		}
	}
	
	public function setColor($color) {
		
		$host = $this->getConfiguration('host');
		$port = $this->getConfiguration('port');
		$cmd_info = $this->getCmd(null,'instance');
        if (is_object($cmd_info)) {
			$instanceId = $cmd_info->execCmd();
			
			callHyperion('/setColor?server=' . $host . ':' . $port . '&instance=' . $instanceId . '&color=' . $color);
			
			sleep(5);
			$this->callUpdateInstanceInfo($instanceId);
		}
	}
	
	public function setClear() {
		
		$host = $this->getConfiguration('host');
		$port = $this->getConfiguration('port');
		$cmd_info = $this->getCmd(null,'instance');
        if (is_object($cmd_info)) {
			$instanceId = $cmd_info->execCmd();
            callHyperion('/setClear?server=' . $host . ':' . $port . '&instance=' . $instanceId);
			
			sleep(5);
			$this->callUpdateInstanceInfo($instanceId);
		}
	}
	
	public function saveSourcesListeState($state) {
		
		$cmd_info = $this->getCmd(null,'source');
        if (is_object($cmd_info)) {
            if ($cmd_info->formatValue($state) != $cmd_info->execCmd()) {
                $cmd_info->setCollectDate('');
                $cmd_info->event($state);
            }
        }
	}
	
	public function saveEffectListeState($effect) {
		
		$cmd_info = $this->getCmd(null,'effect');
        if (is_object($cmd_info)) {
            if ($cmd_info->formatValue($effect) != $cmd_info->execCmd()) {
                $cmd_info->setCollectDate('');
                $cmd_info->event($effect);
            }
        }
	}
    
 // Fonction exécutée automatiquement avant la création de l'équipement 
    public function preInsert() {
        
    }

 // Fonction exécutée automatiquement après la création de l'équipement 
    public function postInsert() {
        
    }

 // Fonction exécutée automatiquement avant la mise à jour de l'équipement 
    public function preUpdate() {
        
    }

 // Fonction exécutée automatiquement après la mise à jour de l'équipement 
    public function postUpdate() {
        
		$this->createOrUpdateCommand();
		self::updateServers();
    }

 // Fonction exécutée automatiquement avant la sauvegarde (création ou mise à jour) de l'équipement 
    public function preSave() {
        
    }

 // Fonction exécutée automatiquement après la sauvegarde (création ou mise à jour) de l'équipement 
    public function postSave() {
		
    }

 // Fonction exécutée automatiquement avant la suppression de l'équipement 
    public function preRemove() {
        
    }

 // Fonction exécutée automatiquement après la suppression de l'équipement 
    public function postRemove() {
        
		self::updateServers();
    }

    /*
     * Non obligatoire : permet de modifier l'affichage du widget (également utilisable par les commandes)
      public function toHtml($_version = 'dashboard') {

      }
     */

    /*
     * Non obligatoire : permet de déclencher une action après modification de variable de configuration
    public static function postConfig_<Variable>() {
    }
     */

    /*
     * Non obligatoire : permet de déclencher une action avant modification de variable de configuration
    public static function preConfig_<Variable>() {
    }
     */

    /*     * **********************Getteur Setteur*************************** */
}

class hyperionNgCmd extends cmd {
    /*     * *************************Attributs****************************** */
    
    /*
      public static $_widgetPossibility = array();
    */
    
    /*     * ***********************Methode static*************************** */


    /*     * *********************Methode d'instance************************* */

    /*
     * Non obligatoire permet de demander de ne pas supprimer les commandes même si elles ne sont pas dans la nouvelle configuration de l'équipement envoyé en JS
      public function dontRemoveCmd() {
      return true;
      }
     */

    // Exécution d'une commande  
    public function execute($_options = array()) {
        
        $eqLogic = $this->getEqLogic();
        if (!is_object($eqLogic) || $eqLogic->getIsEnable() != 1) {
            throw new Exception(__('Equipement desactivé impossible d\éxecuter la commande : ' . $this->getHumanName(), __FILE__));
        }
		log::add('hyperionNg','debug','command: '.$this->getLogicalId().' parameters: '.json_encode($_options));
		switch ($this->getLogicalId()) {
			case "refresh":
				$eqLogic->updateServer();
				break;
            case "liste_instances":
                $eqLogic->callUpdateInstanceInfo($_options['select']);
                $eqLogic->saveInstanceListeState($_options['select']);
                break;
            case "start_instance":
                $eqLogic->toggleStateInstance();
                break;
            case "set_all_server":
			case "set_leddevice_server":
			case "set_v4l_server":
			case "set_grabber_server":
                $eqLogic->toggleComponent(-1, $this->getName());
				sleep(5);
				$eqLogic->updateServer();
                break;
			case "liste_effects":
				$eqLogic->saveEffectListeState($_options['select']);
				break;
			case "liste_sources":
				$eqLogic->callUpdateSourceInfo($_options['select']);
				$eqLogic->saveSourcesListeState($_options['select']);
				break;
			case "source_force":
				$cmd_info = $eqLogic->getCmd(null,'source');
				if (is_object($cmd_info)) {
					$key = $cmd_info->execCmd();
					$eqLogic->setSource($key);
				}
				break;
			case "source_autoselect":
				$eqLogic->setSource('AUTOSELECT');
				break;
			case "effect_cmd":
				$cmd_info = $eqLogic->getCmd(null,'effect');
				if (is_object($cmd_info)) {
					$effect = $cmd_info->execCmd();
					$eqLogic->setEffect($effect);
				}
				break;
			case "color_cmd":
				$cmd_info = $eqLogic->getCmd(null,'color');
				if (is_object($cmd_info)) {
					$color = $cmd_info->getLastValue();
					$eqLogic->setColor(str_replace("#", "", $color));
				}
				break;
			case "clear_cmd":
				$eqLogic->setClear();
				break;
		}
		$eqLogic->refreshWidget();
        return true;
    }

    /*     * **********************Getteur Setteur*************************** */
}


