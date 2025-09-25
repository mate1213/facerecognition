export class Persons {
    constructor(baseUrl) {
        this._baseUrl = baseUrl;
        this._persons = [];
        this._activePerson = undefined;
        this._clustersByName = [];
        this._unassignedClusters = [];
        this._ignoredClusters = [];
        this._loaded = false;
        this._mustReload = false;
    }

    isLoaded() { return this._loaded; }
    mustReload() { return this._mustReload; }
    getPersons() { return this._persons; }
    getActivePerson() { return this._activePerson; }
    getClustersByName() { return this._clustersByName; }
    getUnassignedClusters() { return this._unassignedClusters; }
    getIgnoredClusters() { return this._ignoredClusters; }

    unsetActive() {
        this._activePerson = undefined;
        this._clustersByName = [];
    }

    load() {
        const deferred = $.Deferred();

        $.ajax({
            url: this._baseUrl + '/persons',
            type: 'GET',
            headers: {
                'OC-RequestToken': OC.requestToken,
                'X-Requested-With': 'XMLHttpRequest'
            }
        }).done((response) => {
            this._persons = response.persons.sort((a, b) => b.count - a.count);
            this._loaded = true;
            this._mustReload = false;
            deferred.resolve();
        }).fail(() => {
            deferred.reject();
        });

        return deferred.promise();
    }

    loadPerson(personName) {
        this.unsetActive();
        const deferred = $.Deferred();

        $.ajax({
            url: this._baseUrl + '/person/' + encodeURIComponent(personName),
            type: 'GET',
            headers: {
                'OC-RequestToken': OC.requestToken,
                'X-Requested-With': 'XMLHttpRequest'
            }
        }).done((person) => {
            this._activePerson = person;
            deferred.resolve();
        }).fail(() => {
            deferred.reject();
        });

        return deferred.promise();
    }
}
