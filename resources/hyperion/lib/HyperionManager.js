const axios = require('axios');
const parser = require('fast-xml-parser');

const Hyperion = require('./Hyperion');

class HyperionManager {

    constructor() {
        this.hyperionServers = new Map();
    }
	
	closeAllConnections() {
		
		for (const server of this.hyperionServers.values()) {
			
			server.reset();
		}
		this.hyperionServers.clear();
	}
	
	updateServerList(servers) {
		
		this.closeAllConnections();
		
		if (typeof servers !== 'undefined') {
			
			for (const server of servers) {
				
				if (server['host'] !== '' && server['port'] !== '') {
					
					var id = server['host'] + ':' + server['port']
					if (!this.hyperionServers.has(id)) {
					
						var hyperion = new Hyperion(server['host'], server['port'], server['name']);
						this.hyperionServers.set(id, hyperion);
						hyperion.connect(server['token']);
					}
					else {
					
						var hyperion = this.hyperionServers.get(id);
						if (!hyperion.isConnected()) {
						
							hyperion.connect(server['token']);
						}
					}
				}
			}
		}
	}
    
    getAllServersToJson() {
		
        let servers = [];
        for (const [id, server] of this.hyperionServers) {
            
            servers[id] = server.toArray();
        }
		return Object.assign({}, servers);
	}
	
	getServerToJson(id) {
		
		if (this.hyperionServers.has(id)) {
			
			var server = this.hyperionServers.get(id);
			return Object.assign({}, server.toArray());
		}
		return null;
	}
	
	removeServer(id) {
		
		if (this.hyperionServers.has(id)) {
			
			var server = this.hyperionServers.get(id);
			if (typeof server !== 'undefined') {
				
				server.reset();
			}
			this.hyperionServers.delete(id);
		}
	}
    
    getInstanceToJson(id, instanceId) {
        
        if (this.hyperionServers.has(id)) {
			
			var server = this.hyperionServers.get(id);
			return Object.assign({}, server.getInstanceToJson(Number(instanceId)));
		}
        return null;
    }
	
	getSourceToJson(id, instanceId, key) {
		
		if (this.hyperionServers.has(id)) {
			
			var server = this.hyperionServers.get(id);
			var instance = server.getInstance(Number(instanceId));
			if (instance !== null) {
				
				return instance.getSource(key);
			}
		}
		return null;
	}
	
	setComponentState(id, instanceId, componentId, state) {
		
		var server = this.hyperionServers.get(id);
		if (typeof server !== 'undefined') {
			
			return server.setComponentState(Number(instanceId), componentId, state);
		}
		return false;
	}
	
	setInstance(id, instanceId, state) {
		
		var server = this.hyperionServers.get(id);
		if (typeof server !== 'undefined') {
			
			return server.setInstance(Number(instanceId), state);
		}
		return false;
	}
	
	setSource(id, instanceId, key) {
		
		var server = this.hyperionServers.get(id);
		if (typeof server !== 'undefined') {
			
			return server.setSource(Number(instanceId), key);
		}
		return false;
	}
	
	setEffect(id, instanceId, effectName, duration) {
		
		var server = this.hyperionServers.get(id);
		if (typeof server !== 'undefined') {
			
			return server.setEffect(Number(instanceId), effectName, duration);
		}
		return false;
	}
	
	setColor(id, instanceId, color, duration) {
		
		var server = this.hyperionServers.get(id);
		if (typeof server !== 'undefined') {
			
			return server.setColor(Number(instanceId), color, duration);
		}
		return false;
	}
	
	clearEffect(id, instanceId) {
		
		var server = this.hyperionServers.get(id);
		if (typeof server !== 'undefined') {
			
			return server.clearEffect(Number(instanceId));
		}
		return false;
	}
}

module.exports = HyperionManager;