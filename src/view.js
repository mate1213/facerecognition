import Handlebars from 'handlebars/runtime'
import lozad from 'lozad'
import { getPersonNameUrl, setPersonNameUrl } from './helpers'

import clustersByNamePart from './partials/clustersByNamePart.handlebars'
import bulkAssignerPart from './partials/bulkAssignerPart.handlebars'
import loadedPart from './partials/loadedPart.handlebars'
import loadingPart from './partials/loadingPart.handlebars'
import peoplePart from './partials/peoplePart.handlebars'
import singlePersonPart from './partials/singlePersonPart.handlebars'

export default class View {
    constructor(persons) {
        this._persons = persons
        this._observer = lozad()
    }

    async renderContent() {
        const $el = $('#facerecognition-content')
        $el.empty()
        $el.append(loadingPart())

        if (!this._persons.isLoaded()) return

        if (this._persons.getActivePerson()) {
            $el.empty().append(singlePersonPart({ person: this._persons.getActivePerson() }))
        } else {
            $el.empty().append(peoplePart({ persons: this._persons.getPersons() }))
        }
        this._observer.observe()
    }
}
