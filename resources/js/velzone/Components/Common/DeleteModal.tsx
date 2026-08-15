import React from 'react';
import { Modal, ModalBody } from 'reactstrap';

interface DeleteModalProps {
    show?: boolean;
    onDeleteClick?: () => void;
    onCloseClick?: () => void;
    recordId?: string;
}

const DeleteModal: React.FC<DeleteModalProps> = ({
    show,
    onDeleteClick,
    onCloseClick,
    recordId,
}) => {
    return (
        <Modal fade={true} isOpen={show} toggle={onCloseClick} centered={true}>
            <ModalBody className="px-5 py-3">
                <div className="mt-2 text-center">
                    <i className="ri-delete-bin-line display-5 text-danger"></i>
                    <div className="fs-15 mx-sm-5 mx-4 mt-4 pt-2">
                        <h4>Are you sure ?</h4>
                        <p className="mx-4 mb-0 text-muted">
                            Are you sure you want to remove this record{' '}
                            {recordId ? recordId : ''} ?
                        </p>
                    </div>
                </div>
                <div className="d-flex justify-content-center mt-4 mb-2 gap-2">
                    <button
                        type="button"
                        className="btn btn-light material-shadow-none w-sm"
                        data-bs-dismiss="modal"
                        onClick={onCloseClick}
                    >
                        Close
                    </button>
                    <button
                        type="button"
                        className="btn btn-danger material-shadow-none w-sm"
                        id="delete-record"
                        onClick={onDeleteClick}
                    >
                        Yes, Delete It!
                    </button>
                </div>
            </ModalBody>
        </Modal>
    );
};

export default DeleteModal;
