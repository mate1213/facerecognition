export default class Persons {
    constructor(baseUrl) {
        this._baseUrl = baseUrl

        this._persons = []
        this._activePerson = undefined
        this._clustersByName = []
        this._unassignedClusters = []
        this._ignoredClusters = []
        this._loaded = false
        this._mustReload = false
    }

    isLoaded() { return this._loaded }
    mustReload() { return this._mustReload }

    load() {
        return $.get(`${this._baseUrl}/persons`).then((response) => {
            this._persons = response.persons.sort((a, b) => b.count - a.count)
            this._loaded = true
            this._mustReload = false
        })
    }

    loadPerson(personName) {
        this.unsetActive()
        return $.get(`${this._baseUrl}/person/${encodeURIComponent(personName)}`)
            .then((person) => { this._activePerson = person })
    }

    getPersons() { return this._persons }
    getActivePerson() { return this._activePerson }

    renamePerson(personName, name) {
        const opt = { name }
        return $.ajax({
            url: `${this._baseUrl}/person/${encodeURIComponent(personName)}`,
            method: 'PUT',
            contentType: 'application/json',
            data: JSON.stringify(opt),
        }).then((person) => {
            this._activePerson = person
            this._mustReload = true
        })
    }

    setVisibility(personName, visibility) {
        const opt = { visible: visibility }
        return $.ajax({
            url: `${this._baseUrl}/person/${encodeURIComponent(personName)}/visibility`,
            method: 'POST',
            contentType: 'application/json',
            data: JSON.stringify(opt),
        }).then(() => { this._mustReload = true })
    }

    loadClustersByName(personName) {
        return $.get(`${this._baseUrl}/clusters/${encodeURIComponent(personName)}`)
            .then((clusters) => {
                this._clustersByName = clusters.clusters.sort((a, b) => b.count - a.count)
            })
    }

    loadUnassignedClusters() {
        this._unassignedClusters = []
        return $.get(`${this._baseUrl}/clusters`)
            .then((clusters) => {
                this._unassignedClusters = clusters.clusters.sort((a, b) => b.count - a.count)
            })
    }

    loadIgnoredClusters() {
        this._ignoredClusters = []
        return $.get(`${this._baseUrl}/clustersIgnored`)
            .then((clusters) => {
                this._ignoredClusters = clusters.clusters.sort((a, b) => b.count - a.count)
            })
    }

    getClustersByName() { return this._clustersByName }
    getUnassignedClusters() { return this._unassignedClusters }
    getIgnoredClusters() { return this._ignoredClusters }

    getNamedClusterById(clusterId) {
        return this._clustersByName.find((cluster) => cluster.id === clusterId)
    }

    renameCluster(clusterId, personName) {
        const opt = { name: personName }
        return $.ajax({
            url: `${this._baseUrl}/cluster/${clusterId}`,
            method: 'PUT',
            contentType: 'application/json',
            data: JSON.stringify(opt),
        }).then(() => {
            this._clustersByName.forEach((cluster) => {
                if (cluster.id === clusterId) cluster.name = personName
            })
            this._mustReload = true
        })
    }

    setClusterVisibility(clusterId, visibility) {
        const opt = { visible: visibility }
        return $.ajax({
            url: `${this._baseUrl}/cluster/${clusterId}/visibility`,
            method: 'POST',
            contentType: 'application/json',
            data: JSON.stringify(opt),
        }).then(() => {
            const index = this._clustersByName.findIndex((cluster) => cluster.id === clusterId)
            if (index >= 0) this._clustersByName.splice(index, 1)
            this._mustReload = true
        })
    }

    unsetActive() {
        this._activePerson = undefined
        this._clustersByName = []
    }
}
