const Connection = require('./Connection');
const Component = require('./Component');
const Priority = require('./Priority');
const Effect = require('./Effect');

const componentsControl = [
	"SMOOTHING",
	"BLACKBORDER",
	"FORWARDER",
	"BOBLIGHTSERVER",
	"GRABBER",
	"V4L",
	"LEDDEVICE",
	"ALL"
];

class Instance {

    constructor(host, port, instanceId, name, running) {
		
		this.host = host;
		this.port = port;
        this.id = instanceId;
		this.name = name;
		this.running = running;
		this.connection = null;
		this.components = new Map();
		this.priorities = new Map();
		this.effects = new Map();
		this.priorities_autoselect = false;
    }
	
	getHost() {
		
		return this.host;
	}
	
	getPort() {
		
		return this.port;
	}
	
	getId() {
		
		return this.id;
	}
	
	getName() {
		
		return this.name;
	}
	
	getRunning() {
		
		return this.running;
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
		
		this.components.clear();
		this.priorities.clear();
		this.effects.clear();
		if (this.connection !== null) {
			
			this.connection.closeConnection();
		}
		this.connection = null;
	}
	
	connect(token) {
		
		if (this.createConnection()) {
			
			this.connection.connect(token);
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
				
				self.connection.sendCommand('{"command" : "instance","subcommand" : "switchTo","instance" : ' + self.id + '}\n');
			});
			return true;
		}
		return false;
	}

	onMessage(msg) {
		
		var messageParsed = JSON.parse(msg.utf8Data);
		switch (messageParsed.command) {
			case 'instance-switchTo':
				this.onInstanceSwitched(messageParsed);
				break;
			case 'serverinfo':
				this.onServerInfoResponse(messageParsed);
				break;
			case 'components-update':
				this.updateComponent(messageParsed.data.name, messageParsed.data.enabled);
				break;
			case 'sessions-update':
				break;
			case 'priorities-update':
				this.resgisterPiorities(messageParsed.data.priorities);
				this.priorities_autoselect = messageParsed.data.priorities_autoselect;
				break;
			case 'effects-update':
				this.registerEffects(messageParsed.data.effects);
				break;
			default:
				break;
		}
	}
	
	onInstanceSwitched(instanceMsg) {
		
		if (instanceMsg.success === true) {
			
			this.connection.sendCommand('{"command":"serverinfo","subscribe":["sessions-update", "components-update", "priorities-update","effects-update"]}\n');
		}
	}
	
	onServerInfoResponse(serverinfoMsg) {
		
		this.resgisterComponents(serverinfoMsg.info.components);
		this.resgisterPiorities(serverinfoMsg.info.priorities);
		this.priorities_autoselect = serverinfoMsg.info.priorities_autoselect;
		this.registerEffects(serverinfoMsg.info.effects);
	}
	
	resgisterComponents(components) {
		
		this.components.clear();
		for (const value of components.values()) {
			
			var component = new Component(value.name, value.enabled);
			this.components.set(value.name, component);
		}
		console.log(this.components.size + ' components on instance ' +  this.id);
		return this.components;
	}
	
	resgisterPiorities(priorities) {
		
		this.priorities.clear();
		for (const value of priorities.values()) {
			
			var priority = new Priority(value.active, value.componentId, value.owner, value.priority, value.visible, value.origin, value.value, value.duration_ms);
			this.priorities.set(value.origin + ':' + value.componentId, priority);
		}
		console.log(this.priorities.size + ' priorities on instance ' +  this.id);
		return this.priorities;
	}
	
	registerEffects(effects) {
		
		this.effects.clear();
		for (const value of effects.values()) {
			
			var effect = new Effect(value.file, value.name, value.script);
			this.effects.set(value.name, effect);
		}
		console.log(this.effects.size + ' effects on instance ' +  this.id);
		return this.effects;
	}
	
	getSource(key) {
		
		if (this.priorities.has(key)) {
			
			return this.priorities.get(key).toArray();
		}
	}
	
	updateComponent(name, enabled) {
		
		var component = this.components.get(name);
		if (typeof component !== 'undefined') {
			
			component.enabled = enabled;
		}
	}
	
	setComponentState(componentId, state) {
		
		if (componentsControl.includes(componentId)) {
			
            if (state == 0 || state == 'off') {
                
                return this.connection.sendCommand('{"command" : "componentstate","componentstate":{"component":"' + componentId + '","state":false}}\n');
            }
            else if (state == 1 || state == 'on') {
                
                return this.connection.sendCommand('{"command" : "componentstate","componentstate":{"component":"' + componentId + '","state":true}}\n');
            }
		}
		return false;
	}
	
	setSource(key) {
		
		if (key === 'AUTOSELECT') {
			
			if (this.priorities_autoselect === false) {
				
				return this.connection.sendCommand('{"command":"sourceselect","auto":true}\n');
			}
		}
		else {
			
			var priority = this.priorities.get(key);
			if (typeof priority !== 'undefined') {
				
				if (priority.isActive()) {
				
					return this.connection.sendCommand('{"command":"sourceselect","priority":' + priority.getPriority() + '}\n');
				}
			}
		}
		return false;
	}
	
	setEffect(effectName, duration) {
		
		var effect = this.effects.get(effectName);
		if (typeof effect !== 'undefined') {		
		
			return this.connection.sendCommand('{"command":"effect","effect":{"name":"' + effectName + '"},"priority":50,"origin":"Jeedom App"}\n');
		}
		return false;
	}
	
	setColor(color, duration) {
		
		return this.connection.sendCommand('{"command":"color","color":[' + color.red + ',' + color.green + ',' + color.blue + '],"priority":50,"origin":"Jeedom App"}\n');
	}
	
	clearEffect() {
		
		return this.connection.sendCommand('{"command":"clear","priority":50}\n');
	}
	
	toArray() {
        
        let instance = [];
        instance['name'] = this.getName();
        instance['running'] = this.getRunning();
        instance['connected'] = this.connection.isConnected();
        instance['authenticated'] = this.connection.isAuthenticated();
        let components = [];
        for (const [name, component] of this.components) {
			
			components[name] = component.toArray();
		}
        instance['components'] = Object.assign({}, components);
        let priorities = [];
        for (const [id, priority] of this.priorities) {
			
			priorities[id] = priority.toArray();
		}
        instance['priorities'] = Object.assign({}, priorities);
        instance['priorities_autoselect'] = this.priorities_autoselect;
        let effects = [];
        for (const [name, effect] of this.effects) {
			
			effects[name] = effect.toArray();
		}
        instance['effects'] = Object.assign({}, effects);
        return Object.assign({}, instance);
    }
}

module.exports = Instance;