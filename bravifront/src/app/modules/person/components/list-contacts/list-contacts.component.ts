import { Component, OnInit } from '@angular/core';
import { DialogService, DynamicDialogConfig, DynamicDialogRef } from 'primeng/dynamicdialog';
import { AddContactComponent } from '../add-contact/add-contact.component';
import { ConfirmationService, MessageService } from 'primeng/api';
import { ContactService } from 'src/app/core/services/contact.service';
import { take } from 'rxjs';

@Component({
  selector: 'app-list-contacts',
  templateUrl: './list-contacts.component.html',
  styleUrls: ['./list-contacts.component.scss']
})
export class ListContactsComponent implements OnInit{
  ref: DynamicDialogRef | undefined;

  isLoading = false;
  item: any[] = [];
  person_id: any;
  params: any;

  constructor(
    public dialogService: DialogService,
    public dynamicDialogRef: DynamicDialogRef,
    private dialogConfig: DynamicDialogConfig,
    private messageService: MessageService,
    private contactService: ContactService, 
    private confirmationService: ConfirmationService,
  ){

  }
  ngOnInit(): void {
    if (this.dialogConfig.data) {
      if (this.dialogConfig.data.id) {
        this.person_id = this.dialogConfig.data.id;
      }
      if (this.dialogConfig.data.item) {
        this.item = this.dialogConfig.data.item;
      }
    }
    console.log(this.item)
  }

  load(){

    this.params = {
      id: this.person_id
    }

    this.contactService
      .get(this.params)
      .pipe(take(1))
      .subscribe(
        (resp: any) => {
          this.item = resp.data.data
        },
        (err) => {
        }
      );
  }

  addContact(){
    const config = { 
      header: 'Adicionar contato',
      width: '25%',
      data: {
        id: this.person_id,
      },
    }
    this.ref = this.dialogService.open(AddContactComponent, config);

    this.ref.onClose.subscribe(result =>{
      if(result){
        this.load()
      }
      }); 
  }


  editContact(contact: any){
    const config = {
      header: 'Editar contato',
      data: {
        item: contact,
      },
      width: '25%',
    };

    this.ref = this.dialogService.open(AddContactComponent, config);

    this.ref.onClose.subscribe(result =>{
      if(result){
        this.load()
      }
      });
  }

  deleteContact(id: any){
    this.confirmationService.confirm({
      message: 'Tem certeza que deseja excluir esta pessoa?',
      header: 'Deletar Pessoa',
      icon: 'pi pi-info-circle',
      accept: () => {
        this.contactService
          .delete(id)
          .pipe(take(1))
          .subscribe(
            (resp: any) => {
              if(resp.success){
                this.messageService.add({ severity: 'success', summary: 'Sucesso', detail: 'Contato deletado com sucesso!!', life: 2000 });
                this.load();
              }
            },
            (err) => {
              this.messageService.add({ severity: 'error', summary: 'Erro', detail: 'Houve algum problema!!', life: 2000 });
            }
            );
          },
          reject: () => {
      },
      });
  }
    
}
