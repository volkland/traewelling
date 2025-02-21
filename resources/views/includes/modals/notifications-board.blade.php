<div class="modal fade bd-example-modal-lg" id="notifications-board" tabindex="-1" role="dialog"
     aria-hidden="true" aria-labelledby="notifications-board-title">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="modal-title fs-4" id="notifications-board-title">
                    {{ __('notifications.title') }}
                </h2>
                <a href="javascript:Notification.toggleAllRead()" class="text-muted"
                   aria-label="{{ __('notifications.mark-all-read') }}"
                >
                    <span aria-hidden="true"><i class="fa-solid fa-check-double"></i></span>
                </a>
                <button type="button" class="btn-close" data-mdb-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div id="notifications-loading" class="text-center text-muted">
                    <div class="spinner-border text-primary" role="status">
                        <span class="visually-hidden">Loading...</span>
                    </div>
                </div>
                <div id="notifications-empty" class="d-none text-center text-muted">
                    <i class="fa-solid fa-envelope fs-1"></i>
                    <p class="fs-5">{{ __('notifications.empty') }}</p>
                </div>
                <div id="notifications-list"></div>
            </div>
        </div>
    </div>
</div>
