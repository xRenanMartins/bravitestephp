import { Component, OnInit } from '@angular/core';
import { DialogService, DynamicDialogRef } from 'primeng/dynamicdialog';
import { take } from 'rxjs';
import { PersonService } from 'src/app/core/services/person.service';
import { AddPersonComponent } from '../../components/add-person/add-person.component';
import { ConfirmationService, MessageService } from 'primeng/api';
import { ListContactsComponent } from '../../components/list-contacts/list-contacts.component';

@Component({
  selector: 'app-person',
  templateUrl: './person.component.html',
  styleUrls: ['./person.component.scss']
})
export class PersonComponent implements OnInit {
  ref: DynamicDialogRef | undefined;
  ref2: DynamicDialogRef | undefined;
  persons: any[] = [];
  isLoading = true;
  params: any;

  constructor(
    private servicePerson: PersonService,
    public dialogService: DialogService,
    private messageService: MessageService,
    private confirmationService: ConfirmationService,
    ) {}

  ngOnInit() {
    this.load()
  }

  load() {
    this.isLoading = true;

    this.servicePerson
      .get(this.params)
      .pipe(take(1))
      .subscribe(
        (resp: any) => {
          this.persons = resp.data
          this.isLoading = false;
        },
        (err) => {
          this.isLoading = false;
        }
      );
  }
  
    addPerson() {
      this.ref = this.dialogService.open(AddPersonComponent, { 
        header: 'Adicionar Pessoa',
        width: '25%',
      });
  
      this.ref.onClose.subscribe(result =>{
        if(result){
          this.load()
          this.messageService.add({severity:'success', summary:'Sucesso', detail:'Pessoa adicionada com sucesso', life: 3000});
        }
        });
    }
  
    editPerson(person: any) {
      const config = {
        header: 'Editar Banner',
        data: {
          item: person,
        },
        width: '25%',
      };

      this.ref = this.dialogService.open(AddPersonComponent, config);
  
      this.ref.onClose.subscribe(result =>{
        if(result){
          this.load()
          this.messageService.add({severity:'success', summary:'Sucesso', detail:'Pessoa atualizada com sucesso', life: 3000});
        }
        });
    }
  
  deletePerson(id: any){
    this.confirmationService.confirm({
      message: 'Tem certeza que deseja excluir esta pessoa?',
      header: 'Deletar Pessoa',
      icon: 'pi pi-info-circle',
      accept: () => {
        this.servicePerson
          .delete(id)
          .pipe(take(1))
          .subscribe(
            (resp: any) => {
              if(resp.success){
                this.messageService.add({ severity: 'success', summary: 'Sucesso', detail: 'Pessoa deletada com sucesso!!', life: 2000 });
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

    listContacts(contacts: any, id: any){
      const config = {
        header: 'Lista de contatos',
        data: {
          item: contacts,
          id: id
        },
        width: '65%',
      };

      this.ref2 = this.dialogService.open(ListContactsComponent, config);
  
      this.ref2.onClose.subscribe(result =>{
        if(result){
          // this.load()
          // this.messageService.add({severity:'success', summary:'Sucesso', detail:'Pessoa adicionada com sucesso', life: 3000});
        }
        });
    }
}
