<template>
    <div :id="id" style="display: contents">
        <div class="datatable-toolbar-top card-body">
            <b-row class="align-items-center mb-2" v-if="showToolbar">
                <b-col xl="7" md="6" sm="12">
                    <b-pagination v-model="currentPage" :total-rows="totalRows" :per-page="perPage"
                                  class="mb-0" v-if="showPagination">
                    </b-pagination>
                </b-col>
                <b-col xl="5" md="6" sm="12" class="d-flex">
                    <div class="flex-fill">
                        <div class="input-group">
                            <span class="icon glyphicon input-group-addon search"></span>
                            <b-form-input debounce="200" v-model="filter" class="search-field form-control"
                                          :placeholder="langSearch"></b-form-input>
                        </div>
                    </div>
                    <div class="flex-shrink-1 pl-3 pr-3">
                        <b-btn-group class="actions">
                            <b-button variant="default" title="Refresh" @click="onClickRefresh" v-b-tooltip.hover
                                      :title="langRefreshTooltip">
                                <i class="material-icons">refresh</i>
                            </b-button>
                            <b-dropdown variant="default" :text="perPageLabel" v-b-tooltip.hover
                                        :title="langPerPageTooltip">
                                <b-dropdown-item v-for="pageOption in pageOptions" :key="pageOption"
                                                 :active="(pageOption === perPage)" @click="setPerPage(pageOption)">
                                    {{ getPerPageLabel(pageOption) }}
                                </b-dropdown-item>
                            </b-dropdown>
                            <b-dropdown variant="default" v-if="selectFields" v-b-tooltip.hover
                                        :title="langSelectFieldsTooltip">
                                <template v-slot:button-content>
                                    <i class="material-icons" aria-hidden="true">filter_list</i>
                                    <span class="caret"></span>
                                </template>
                                <b-dropdown-form class="pt-3">
                                    <div v-for="field in selectableFields" class="form-group">
                                        <div class="custom-control custom-checkbox">
                                            <input type="checkbox" class="custom-control-input"
                                                   v-bind:id="'chk_field_' + field.key" name="is_field_visible"
                                                   v-model="field.visible" @change="storeSettings">
                                            <label class="custom-control-label" v-bind:for="'chk_field_'+field.key">
                                                {{ field.label }}
                                            </label>
                                        </div>
                                    </div>
                                </b-dropdown-form>
                            </b-dropdown>
                        </b-btn-group>
                    </div>
                </b-col>
            </b-row>
        </div>
        <div class="datatable-main table-responsive table-responsive-lg">
            <b-table ref="table" show-empty striped hover :selectable="selectable" :api-url="apiUrl" :per-page="perPage"
                     :current-page="currentPage" @row-selected="onRowSelected" :items="loadItems" :fields="visibleFields"
                     :empty-text="langNoRecords" :empty-filtered-text="langNoRecords"
                     tbody-tr-class="align-middle" thead-tr-class="align-middle" selected-variant=""
                     :filter="filter" @filtered="onFiltered" @refreshed="onRefreshed">
                <template v-slot:head(selected)="data">
                    <b-form-checkbox :aria-label="langSelectAll" v-model="allSelected"
                                     @change="toggleSelected"></b-form-checkbox>
                </template>
                <template v-slot:cell(selected)="{ rowSelected }">
                    <i class="material-icons">
                        <template v-if="rowSelected">check_box</template>
                        <template v-else>check_box_outline_blank</template>
                    </i>
                </template>
                <template v-slot:table-busy>
                    <div role="alert" aria-live="polite">
                        <div class="text-center my-2">
                            <div class="progress-circular progress-circular-primary mx-auto mb-3">
                                <div class="progress-circular-wrapper">
                                    <div class="progress-circular-inner">
                                        <div class="progress-circular-left">
                                            <div class="progress-circular-spinner"></div>
                                        </div>
                                        <div class="progress-circular-gap"></div>
                                        <div class="progress-circular-right">
                                            <div class="progress-circular-spinner"></div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            {{ langLoading }}
                        </div>
                    </div>
                </template>
                <slot v-for="(_, name) in $slots" :name="name" :slot="name"/>
                <template v-for="(_, name) in $scopedSlots" :slot="name" slot-scope="slotData">
                    <slot :name="name" v-bind="slotData"/>
                </template>
            </b-table>
        </div>
        <div class="datatable-toolbar-bottom card-body">
            <b-pagination v-model="currentPage" :total-rows="totalRows" :per-page="perPage"
                          class="mb-0 mt-2" v-if="showPagination">
            </b-pagination>
        </div>
    </div>
</template>

<style lang="scss">
    div.datatable-main {
        flex: 1;
    }

    div.datatable-toolbar-top,
    div.datatable-toolbar-bottom {
        flex: 0;
        padding: 0;
    }

    table.b-table-selectable {
        thead tr th:nth-child(1),
        tbody tr td:nth-child(1) {
            padding-right: 0.75rem;
            width: 3rem;
        }

        thead tr th:nth-child(2),
        tbody tr td:nth-child(2) {
            padding-left: 0.5rem;
        }
    }
</style>

<script>
    import axios from 'axios';
    import store from 'store';
    import _ from 'lodash';

    export default {
        name: 'DataTable',
        props: {
            id: String,
            apiUrl: String,
            paginated: {
                type: Boolean,
                default: false
            },
            showToolbar: {
                type: Boolean,
                default: true
            },
            defaultPerPage: {
                type: Number,
                default: 10
            },
            fields: Array,
            selectable: {
                type: Boolean,
                default: false
            },
            selectFields: {
                type: Boolean,
                default: false
            },
            requestConfig: Function,
            requestProcess: Function
        },
        data () {
            let allFields = [];
            _.forEach(this.fields, function (field) {
                allFields.push({
                    key: field.key,
                    label: _.defaultTo(field.label, ''),
                    sortable: _.defaultTo(field.sortable, false),
                    selectable: _.defaultTo(field.selectable, false),
                    visible: _.defaultTo(field.visible, true),
                    formatter: _.defaultTo(field.formatter, null)
                });
            });

            return {
                allFields: allFields,
                selected: [],
                allSelected: false,
                storeKey: 'datatable_' + this.id + '_settings',
                filter: null,
                perPage: (this.paginated) ? this.defaultPerPage : 0,
                pageOptions: [10, 25, 50, 0],
                currentPage: 1,
                totalRows: 0,
                flushCache: false
            };
        },
        mounted () {
            this.loadStoredSettings();
        },
        computed: {
            langRefreshTooltip () {
                return this.$gettext('Refresh rows');
            },
            langPerPageTooltip () {
                return this.$gettext('Rows per page');
            },
            langSelectFieldsTooltip () {
                return this.$gettext('Select displayed fields');
            },
            langSelectAll () {
                return this.$gettext('Select all visible rows');
            },
            langSelectRow () {
                return this.$gettext('Select this row');
            },
            langSearch () {
                return this.$gettext('Search');
            },
            langNoRecords () {
                return this.$gettext('No records to display.');
            },
            langLoading () {
                return this.$gettext('Loading...');
            },
            visibleFields () {
                let fields = this.allFields.slice();

                if (this.selectable) {
                    fields.unshift({
                        key: 'selected',
                        label: '',
                        sortable: false,
                        selectable: false,
                        visible: true
                    });
                }

                if (!this.selectFields) {
                    return fields;
                }

                return _.filter(fields, (field) => {
                    if (!field.selectable) {
                        return true;
                    }

                    return field.visible;
                });
            },
            selectableFields () {
                return _.filter(this.allFields.slice(), (field) => {
                    return field.selectable;
                });
            },
            showPagination () {
                return this.paginated && this.perPage !== 0;
            },
            perPageLabel () {
                return this.getPerPageLabel(this.perPage);
            }
        },
        methods: {
            loadStoredSettings () {
                if (store.enabled && store.get(this.storeKey) !== undefined) {
                    let settings = store.get(this.storeKey);

                    this.perPage = _.defaultTo(settings.perPage, this.defaultPerPage);

                    _.forEach(this.selectableFields, (field) => {
                        field.visible = _.includes(settings.visibleFields, field.key);
                    });
                }
            },
            storeSettings () {
                if (!store.enabled) {
                    return;
                }

                let settings = {
                    'perPage': this.perPage,
                    'visibleFields': _.map(this.visibleFields, 'key')
                };
                store.set(this.storeKey, settings);
            },
            getPerPageLabel (num) {
                return (num === 0) ? 'All' : num.toString();
            },
            setPerPage (num) {
                this.perPage = num;
                this.storeSettings();
            },
            onClickRefresh (e) {
                if (e.shiftKey) {
                    this.relist();
                } else {
                    this.refresh();
                }
            },
            onRefreshed () {
                this.$emit('refreshed');
            },
            refresh () {
                this.$refs.table.refresh();
            },
            navigate () {
                this.filter = null;
                this.currentPage = 1;
                this.flushCache = true;
                this.refresh();
            },
            relist () {
                this.filter = null;
                this.flushCache = true;
                this.refresh();
            },
            setFilter (newTerm) {
                this.currentPage = 1;
                this.filter = newTerm;
            },
            loadItems (ctx, callback) {
                let queryParams = {
                    internal: true
                };

                if (this.paginated) {
                    queryParams.rowCount = ctx.perPage;
                    queryParams.current = ctx.currentPage;
                } else {
                    queryParams.rowCount = 0;
                }

                if (this.flushCache) {
                    queryParams.flushCache = true;
                }

                if (typeof ctx.filter === 'string') {
                    queryParams.searchPhrase = ctx.filter;
                }

                if ('' !== ctx.sortBy) {
                    queryParams.sort = ctx.sortBy;
                    queryParams.sortOrder = (ctx.sortDesc) ? 'DESC' : 'ASC';
                }

                let requestConfig = { params: queryParams };
                if (typeof this.requestConfig === 'function') {
                    requestConfig = this.requestConfig(requestConfig);
                }

                axios.get(ctx.apiUrl, requestConfig).then((resp) => {
                    this.flushCache = false;
                    this.totalRows = resp.data.total;

                    let rows = resp.data.rows;
                    if (typeof this.requestProcess === 'function') {
                        rows = this.requestProcess(rows);
                    }

                    callback(rows);
                }).catch((err) => {
                    this.flushCache = false;
                    this.totalRows = 0;

                    console.error(err.data.message);
                    callback([]);
                });
            },
            onRowSelected (items) {
                if (this.perPage === 0) {
                    this.allSelected = items.length === this.totalRows;
                } else {
                    this.allSelected = items.length === this.perPage;
                }

                this.selected = items;
                this.$emit('row-selected', items);
            },
            toggleSelected () {
                if (this.allSelected) {
                    this.$refs.table.clearSelected();
                    this.allSelected = false;
                } else {
                    this.$refs.table.selectAllRows();
                    this.allSelected = true;
                }
            },
            onFiltered (filter) {
                this.$emit('filtered', filter);
            }
        }
    };
</script>