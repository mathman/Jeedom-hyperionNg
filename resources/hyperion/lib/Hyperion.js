const WebSocketClient = require('websocket').client;
const Instance = require('./Instance');
const Connection = require('./Connection');

class Hyperion {

    constructor(host, port, name) {
		
        this.host = host;
		this.port = port;
        this.name = name;
		this.instances = new Map();
		this.connection = null;
		this.token = null;
		this.hyperionSysInfo = "";
    }
	
	getHost() {
		
		return this.host;
	}
	
	getPort() {
		
		return this.port;
	}
    
    getName() {
        
        return this.name;
    }
	
	getInstance(instanceId) {
		
		if (this.instances.has(instanceId)) {
            
            return this.instances.get(instanceId);
        }
        return null;
	}
	
	getInstanceToJson(instanceId) {
        
        if (this.instances.has(instanceId)) {
            
            return Object.assign({}, this.instances.get(instanceId).toArray());
        }
        return null;
    }
	
	isConnected() {
		
		if (this.connection !== null) {
			
			return this.connection.isConnected();
		}
		return false;
	}
	
	isAuthenticated() {
		
		if (this.connection !== null) {
			
			return this.connection.isAuthenticated();
		}
		return false;
	}
	
	reset() {
		
		for (const instance of this.instances.values()) {
			
			instance.reset();
		}
		this.instances.clear();
		if (this.connection !== null) {

			this.connection.closeConnection();
		}
		this.connection = null;
		this.token = null;
		this.hyperionSysInfo = "";
	}
	
	connect(token) {
		
		this.token = token;
		if (this.createConnection()) {
			
			this.connection.connect(this.token);
		}
	}
	
	createConnection() {
		
		if (this.connection === null) {
			
			this.connection = new Connection(this.getHost(), this.getPort());
			var self = this;
			this.connection.on('message', function(message) {
			
				self.onMessage(message);
			});
			this.connection.on('authenticated', function() {
			
				self.connection.sendCommand('{"command":"serverinfo","subscribe":["instance-update"]}\n');
			});
		}
		return true;
	}
	
	onMessage(msg) {
		
		var messageParsed = JSON.parse(msg.utf8Data);
		switch (messageParsed.command) {
			case 'serverinfo':
				this.registerInstances(messageParsed.info.instance);
				this.sendSysInfoCommand();
				break;
			case 'sysinfo':
				this.updateSystemInfo(messageParsed.info);
				break;
			case 'instance-update':
				this.registerInstances(messageParsed.data);
				break;
			default:
				break;
		}
	}
	
	registerInstances(instances) {
		
		for (const instance of this.instances.values()) {
			
			instance.reset();
		}
		this.instances.clear();
		for (const value of instances.values()) {
			
			var instance = new Instance(this.host, this.port, value.instance, value.friendly_name, value.running);
			this.instances.set(value.instance, instance);
		}
		console.log(this.instances.size + ' instances on server ' +  this.name);
        
        this.connectInstances();
		
		return this.instances;
	}
	
	connectInstances() {
		
		if (this.isConnected() && this.isAuthenticated()) {
			
			for (const instance of this.instances.values()) {
			
				if (!instance.isConnected()) {
					
					if (instance.getRunning() === true) {
				
						instance.connect(this.token);
					}
				}
			}
		}
	}
	
	sendSysInfoCommand() {
		
		if (this.connection !== null) {
			
			this.connection.sendCommand('{"command":"sysinfo"}\n');
		}
	}
	
	updateSystemInfo(sysInfo) {
		
		this.hyperionSysInfo = sysInfo;
	}
	
	setInstance(instanceId, state) {
		
		if (instanceId < 0) {
			
			for (const instance of this.instances.values()) {
                
                if (state == 0 || state == 'off') {
                    
                    this.connection.sendCommand('{"command" : "instance","subcommand" : "stopInstance","instance" : ' + instance.id + '}\n');
                }
                else if (state == 1 || state == 'on') {
                    
                    this.connection.sendCommand('{"command" : "instance","subcommand" : "startInstance","instance" : ' + instance.id + '}\n');
                }
			}
			return true;
		}
		else {
			
            if (state == 0 || state == 'off') {
                
                return this.connection.sendCommand('{"command" : "instance","subcommand" : "stopInstance","instance" : ' + instanceId + '}\n');
            }
            else if (state == 1 || state == 'on') {
                
                return this.connection.sendCommand('{"command" : "instance","subcommand" : "startInstance","instance" : ' + instanceId + '}\n');
            }
		}
	}
	
	setComponentState(instanceId, componentId, state) {
		
		if (instanceId < 0) {
				
			for (const instance of this.instances.values()) {
			
				instance.setComponentState(componentId, state);
			}
			return true;
		}
		else {
            
			var instance = this.instances.get(instanceId);
			if (typeof instance !== 'undefined') {
				
				return instance.setComponentState(componentId, state);
			}
		}
		return false;
	}
	
	setSource(instanceId, key) {
		
		if (instanceId < 0) {
				
			for (const instance of this.instances.values()) {
			
				instance.setSource(key);
			}
			return true;
		}
		else {
			
			var instance = this.instances.get(instanceId);
			if (typeof instance !== 'undefined') {
				
				return instance.setSource(key);
			}
		}
		return false;
	}
	
	setEffect(instanceId, effectName, duration) {
		
		if (instanceId < 0) {
				
			for (const instance of this.instances.values()) {
			
				instance.setEffect(effectName, duration);
			}
			return true;
		}
		else {
			
			var instance = this.instances.get(instanceId);
			if (typeof instance !== 'undefined') {
				
				return instance.setEffect(effectName, duration);
			}
		}
		return false;
	}
	
	setColor(instanceId, color, duration) {
		
		if (instanceId < 0) {
				
			for (const instance of this.instances.values()) {
			
				instance.setColor(color, duration);
			}
			return true;
		}
		else {
			
			var instance = this.instances.get(instanceId);
			if (typeof instance !== 'undefined') {
				
				return instance.setColor(color, duration);
			}
		}
		return false;
	}
	
	clearEffect(instanceId) {
		
		if (instanceId < 0) {
				
			for (const instance of this.instances.values()) {
			
				instance.clearEffect();
			}
			return true;
		}
		else {
			
			var instance = this.instances.get(instanceId);
			if (typeof instance !== 'undefined') {
				
				return instance.clearEffect();
			}
		}
		return false;
	}
	
	toArray() {
        
        let server = [];
        server['host'] = this.getHost();
        server['port'] = this.getPort();
        server['name'] = this.getName();
		server['sysInfo'] = this.hyperionSysInfo;
        server['connected'] = this.connection.isConnected();
        server['authenticated'] = this.connection.isAuthenticated();
        let instances = [];
        for (const [instanceId, instance] of this.instances) {
			
			instances[instanceId] = instance.toArray();
		}
        server['instances'] = instances;
        return Object.assign({}, server);
    }
}

module.exports = Hyperion;